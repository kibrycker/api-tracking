### api-tracking
## Поиск посылок и бандеролей по трэк-номеру

#### Подключение
Задаем минимальную конфигурацию например в **tracking.php**
```php
<?php

use kibrycker\api\tracking\Module;

return [
    'bootstrap' => ['api-tracking'],
    /** aliases можно не задавать если модуль установлен через composer */
    'aliases' => [
        '@kibrycker/api/tracking' => '@app/modules/api-tracking/src'
    ],
    'modules' => [
        'api-tracking' => [
            'class' => Module::class,
            'apiLogin' => 'LOGIN',
            'apiPassword' => 'PASSWORD',
        ]
    ]
];
```
, где
- apiLogin - Логин для доступа к API трекинга
- apiPassword - Пароль для доступа к API трекинга

и подключаем через **ArrayHelper::merge()** в **configs/config-local.php**

```php
return ArrayHelper::merge(
    [... общая конфигурауия ...],
    include __DIR__ . '/modules/tracking.php'
)
```

### Получение данных по отправлениям через Единичный доступ
```json
POST URL_TO_SITE/api-tracking/default
Content-Type: application/json

{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "single-check",
  "params": {
    "barcode": "RA644000001RU",
    "messageType": 0,
    "language": "RUS"
  }
}
```
, где
- barcode - Идентификатор регистрируемого почтового отправления в одном из форматов (Обязательный параметр):
  - внутрироссийский, состоящий из 14 символов (цифровой);
  - международный, состоящий из 13 символов (буквенно-цифровой) в формате S10.
- messageType - Тип сообщения. Возможные значения:
  - 0 - история операций для отправления;
  - 1 - история операций для заказного уведомления по данному отправлению.
- language - Язык, на котором должны возвращаться названия операций/атрибутов и сообщения об ошибках. Допустимые значения:
  - RUS – использовать русский язык (используется по умолчанию);
  - ENG – использовать английский язык.