<?php

namespace kibrycker\api\tracking;

use Yii;
use yii\base\Application;
use yii\base\BootstrapInterface;
use yii\base\InvalidConfigException;
use yii\base\InvalidArgumentException;
use yii\i18n\PhpMessageSource;

class Module extends \yii\base\Module implements BootstrapInterface
{
    /** @var string Схема WSDL для единичного доступа, по которой нужно получить данные */
    const API_SINGLE_WSDL = 'https://tracking.russianpost.ru/rtm34?wsdl';

    /** @var string Схема WSDL для пакетного доступа, по которой нужно получить данные */
    const API_MULTI_WSDL = 'https://tracking.russianpost.ru/fc?wsdl';

    /** @var int Базовый протокол */
    const SOAP_VERSION = SOAP_1_2;

    /** @var int  */
    const TRACE_SOAP = 1;

    /** @var int Максимальное количество итераций для авторизации */
    const MAX_ITERATION = 5;

    /** @var string Логин для доступа к API Сервиса отслеживания  */
    public string $apiLogin;

    /** @var string Пароль для доступа к API Сервиса отслеживания */
    public string $apiPassword;

    /**
     * Загрузчик модуля
     *
     * @param Application $app Текущее приложение
     * @throws InvalidConfigException
     */
    public function bootstrap($app): void
    {
        $this->registerTranslations();
    }

    /**
     * Логирование сообщений отладки
     * @param string $type Тип сообщения: debug, info, warning, error
     * @param string $message Текст сообщения отладки
     * @param string|null $category Категория сообщения отладки. Если =null, то берется по имени модуля ($this->id)
     */
    public static function log(string $type, string $message, string $category = null): void
    {
        if (!in_array($type, ['debug', 'info', 'warning', 'error'])) {
            throw new InvalidArgumentException('Invalid log message type');
        }
        Yii::$type($message, $category ?? Module::getInstance()->id);
    }

    /**
     * Получение экземпляра модуля
     * @return Module|null
     */
    public static function getInstance(): ?Module
    {
        $module = parent::getInstance();
        if ($module === null) {
            throw new \RuntimeException('Failed to instantiate `' . static::class . '` object');
        }
        return $module;
    }

    /**
     * Добавление переводов для модуля в систему
     */
    private function registerTranslations(): void
    {
        if (!empty(Yii::$app->i18n->translations[$this->id . '*'])) {
            // переводы уже зарегистрированы
            return;
        }
        $fileMap = [];
        foreach (['main', 'errors'] as $name) {
            $fileMap[$this->id . '/' . $name] = $name . '.php';
        }
        Yii::$app->i18n->translations[$this->id . '*'] = [
            'class' => PhpMessageSource::class,
            'sourceLanguage' => 'en-US',
            'basePath' => __DIR__ . '/messages',
            'fileMap' => $fileMap,
        ];
    }

    /**
     * Метод получения перевода строки
     * @param string $category Категория строки для перевода
     * @param string $message Исходная строка для перевода
     * @param array $params Параметры используемые для замены соответствующих плейсхолдеров в строке
     * @param null $language Код языка перевода (например: `en-US`, `en`). Если null, то текущий язык системы
     * @return string
     */
    public static function t(string $category, string $message, array $params = [], $language = null): string
    {
        return Yii::t(Module::getInstance()->id . '/' . $category, $message, $params, $language);
    }

}