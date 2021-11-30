<?php

namespace kibrycker\api\tracking\controllers;

use JsonRpc2\Exception;
use kibrycker\api\tracking\Module;
use JsonRpc2\Controller;
use yii\helpers\Json;

class DefaultController extends Controller
{
    /** @var string|null Объединение адреса API со схемой WSDL */
    public ?string $api;
    /** @var int Код ошибки */
    public int $apiAuthErrorCode;
    /** @var array Набор параметров для запроса API */
    public array $queryParams = [];
    /** @var string|null Метод API, к которому идет запрос */
    public ?string $method;
    /** @var string|null Название параметра, передаваемого в SoapParam */
    public ?string $paramName;
    /** @var \SoapClient|null Объект SOAP */
    protected ?\SoapClient $clientAPI = null;
    /** @var int Итерация */
    protected int $iteration = 0;

    /**
     * Тестовое действие для проверки работы модуля
     *
     * @return string
     */
    public function actionTest(): string
    {
        return 'Default:Test';
    }

    /**
     * Получение данных по единичному доступу
     *
     * @param string $barcode  Идентификатор регистрируемого почтового отправления в одном из форматов:
     *                         - внутрироссийский, состоящий из 14 символов (цифровой);
     *                         - международный, состоящий из 13 символов (буквенно-цифровой) в формате S10.
     * @param int $messageType Тип сообщения. Возможные значения:
     *                         0 - история операций для отправления;
     *                         1 - история операций для заказного уведомления по данному отправлению.
     * @param string $language Язык, на котором должны возвращаться названия операций/атрибутов и сообщения об ошибках.
     *                         Допустимые значения:
     *                         RUS – использовать русский язык (используется по умолчанию);
     *                         ENG – использовать английский язык.
     *
     * @return []|array|null
     * @throws Exception
     * @throws \HttpException
     */
    public function actionSingleCheck(string $barcode, int $messageType = 0, string $language = 'RUS'): array
    {
        $this->api = Module::API_SINGLE_WSDL;
        if (empty($barcode)) {
            throw new Exception(Module::t('error', 'Не передан код'));
        }
        $this->queryParams = [
            'OperationHistoryRequest' => [
                'Barcode' => $barcode,
                'MessageType' => $messageType,
                'Language' => $language,
            ],
            'AuthorizationHeader' => [
                'login' => Module::getInstance()->apiLogin,
                'password' => Module::getInstance()->apiPassword,
            ],
        ];

        $this->method = 'getOperationHistory';
        $this->paramName = 'OperationHistoryRequest';
        return $this->request();
    }

    /**
     * Выполнение запроса к API
     *
     * @return array|null
     * @throws \HttpException
     */
    protected function request(): ?array
    {
        if (!$this->method) {
            return [];
        }

        try {
            $client = $this->getClientAPI();
            $function = $this->method;
            $result = $client->$function(new \SoapParam($this->queryParams,$this->paramName));
        } catch (\SoapFault $e) {
            if (property_exists($e->detail, 'error')) {
                return $this->objectToArray($e->detail->error);
            }
            throw $e;
        }

        if (!empty($result->detail->error)) {
            if ($result->detail->error->code == $this->apiAuthErrorCode) {
                return $this->tryRepeatRequest('Error Authorization in Request', 502);
            } else {
                throw new \HttpException('Error in Request: ' . $result->detail->error->msg, 402);
            }
        }

        /** Когда есть положительный ответ надо сбросить счетчик итераций */
        $this->iteration = 0;
        return $this->objectToArray($result);
    }

    /**
     * Реализация объекта SOAP клиента API
     *
     * @return \SoapClient|null
     * @throws \SoapFault
     */
    protected function getClientAPI(): ?\SoapClient
    {
        if ($this->clientAPI === null) {
            if (PHP_VERSION_ID < 80000) {
                libxml_disable_entity_loader(false);
            }
            $options = [
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                ]),
                'trace' => Module::TRACE_SOAP,
                'soap_version' => Module::SOAP_VERSION
            ];
            $this->clientAPI = new \SoapClient($this->api, $options);
        }

        return $this->clientAPI;
    }

    /**
     * Обертка для рекурсивного обращения к API. Попытка пробиться ;)
     *
     * @param string $msg  Сообщение об ошибке
     * @param int    $code Код ошибки
     *
     * @return array
     * @throws \Exception
     */
    protected function tryRepeatRequest(string $msg, int $code): array
    {
        $this->iteration++;
        if ($this->iteration > Module::MAX_ITERATION) {
            throw new \HttpException($code, $msg);
        }
        return $this->request();
    }

    /**
     * Перевод данных из объекта в массив
     *
     * @param object $data Объект \StdClass, который нужно преобразовать в массив
     *
     * @return array
     */
    protected function objectToArray(object $data): array
    {
        if (gettype($data) == 'object') {
            $data = Json::encode($data);
        }

        if (gettype($data) == 'string') {
            $data = Json::decode($data, true);
        }

        return $data;
    }

}