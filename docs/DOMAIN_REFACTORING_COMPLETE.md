# Рефакторинг доменного слоя - Завершен

## Обзор изменений

Выполнен полный рефакторинг доменного слоя согласно ТЗ1 и ТЗ2 для унификации результатов операций и структурирования данных проверки марки.

## ✅ Выполненные задачи

### 1. Унификация результата: только `OperationResult` (ТЗ1)

**Изменения в `src/domain/model/OperationResult.php`:**
- Исправлен порядок параметров в статических методах согласно ТЗ
- `success(?array $data = null, ?string $message = null)` 
- `failure(string $error, ?array $data = null)`

### 2. Банковские операции (ТЗ1)

**Обновлен `src/domain/service/ProcessBankPaymentUseCase.php`:**
- Все методы (`pay`, `refund`, `closeShift`) теперь возвращают `OperationResult`
- Слип банковской операции возвращается в `data['slip']` (доменная логика)
- Код результата банка в `data['result_code']`
- Убраны технические детали печати (`printed_lines` и подобное)

**Пример успешной операции:**
```php
OperationResult::success([
    'slip' => $slipLines,        // Слип для клиента/банка
    'result_code' => $resultCode // Код результата банка
], 'Операция успешно завершена');
```

### 3. Операции с весами

**Обновлен `src/domain/service/GetWeightUseCase.php`:**
- Возвращает `OperationResult` с весом в `data['weight']`
- Преобразует результаты весов в доменный формат

### 4. Проверка марки - Структурированный результат (ТЗ2)

**Обновлены:**
- `src/domain/service/ValidateMark.php`
- `src/domain/service/SendToHonestSignUseCase.php`

**Новая структура результата проверки марки:**
```php
[
    'mode' => 'permit|ecr',           // Тип проверки
    'user_status' => [                // Для пользователя/оператора
        'ok' => true|false,           // Можно ли продавать
        'text' => 'Описание статуса'  // Что показать оператору
    ],
    'machine_data' => [               // Машинные данные
        // Для permit: uuid, time, и др.
        // Для ecr: itemInfoCheckResult с флагами ККТ
    ]
]
```

**Поддерживаемые режимы:**
- `permit` - разрешительный режим (uuid, time для чека)
- `ecr` - проверка ККТ (itemInfoCheckResult для интеграции)

### 5. Очистка устаревших моделей

**Удалены файлы:**
- `src/domain/model/BankResult.php` ❌ (заменен на OperationResult)
- `src/domain/model/WeightResult.php` ❌ (заменен на OperationResult) 
- `src/domain/model/MarkSignResult.php` ❌ (заменен на OperationResult)

## 📋 Принципы реализации

### Доменные данные в `data`
- **Банк:** `slip` (слип банка) + `result_code`
- **Весы:** `weight` (вес товара)
- **Марка:** `mode` + `user_status` + `machine_data`

### Отсутствие технических деталей
- ❌ Никаких `printed_lines` в домене
- ❌ Никаких инфраструктурных деталей печати
- ✅ Только бизнес-логика и доменные данные

### Структурированность
- `user_status` - для отображения оператору
- `machine_data` - для передачи в ККТ/чек/сервер
- Четкое разделение user-friendly и machine-friendly данных

## 🔄 Обратная совместимость

Все use case'ы теперь возвращают `OperationResult`, что может потребовать обновления:
- Контроллеров API (`src/api/`)
- Адаптеров инфраструктуры
- Presenter'ов и formatter'ов

## 📖 Примеры использования

### Банковская операция
```php
$result = $bankUseCase->pay(100.0);
if ($result->success) {
    $slip = $result->getData('slip');
    $resultCode = $result->getData('result_code');
}
```

### Проверка марки
```php
$result = $validateUseCase->validateMark($code, 'permit');
if ($result->success) {
    $userStatus = $result->getData('user_status');
    $machineData = $result->getData('machine_data');
    
    echo $userStatus['text']; // Показать оператору
    // Использовать $machineData для чека
}
```

### Взвешивание
```php
$result = $weightUseCase->execute();
if ($result->success) {
    $weight = $result->getData('weight');
}
```

## ✅ Соответствие ТЗ

- **ТЗ1:** ✅ Все use case'ы возвращают только `OperationResult`
- **ТЗ1:** ✅ Слип банка в `data['slip']` как доменная логика
- **ТЗ1:** ✅ Убраны технические детали печати из домена
- **ТЗ2:** ✅ Структурированный результат проверки марки
- **ТЗ2:** ✅ Разделение user-friendly и machine-friendly данных
- **ТЗ2:** ✅ Поддержка режимов `permit` и `ecr`

Доменный слой полностью соответствует принципам чистой архитектуры и требованиям ТЗ.