# Новая архитектура проверки марки

## Обзор

Проверка марки теперь разбита на два независимых сценария согласно новому ТЗ:

- **Разрешительный режим (permit):** синхронно, через внешний API
- **Проверка на ККТ (ecr):** асинхронно, через очередь, статус по taskId

## Интерфейсы

### PermitMarkCheckGateway
```php
interface PermitMarkCheckGateway
{
    public function checkPermit(MarkingCode $code): OperationResult;
}
```

### EcrMarkCheckGateway
```php
interface EcrMarkCheckGateway
{
    public function enqueueMarkCheck(MarkingCode $code): string;
    public function getMarkCheckResult(string $taskId): OperationResult;
}
```

## Use Cases

### PermitMarkCheckUseCase
Синхронная проверка марки в разрешительном режиме:
```php
$useCase = new PermitMarkCheckUseCase($gateway);
$result = $useCase->execute($markingCode);
```

### EcrMarkCheckUseCase
Асинхронная проверка марки на ККТ:
```php
$useCase = new EcrMarkCheckUseCase($gateway);

// Постановка в очередь
$taskId = $useCase->enqueue($markingCode);

// Получение результата
$result = $useCase->getResult($taskId);
```

## Реализации

### HttpPermitMarkCheckGateway
Синхронная реализация для разрешительного режима через HTTP API.

### QueueEcrMarkCheckGateway
Асинхронная реализация для ККТ через очередь с taskId.

## API Endpoints

### Разрешительный режим
```
POST /api/permit-mark-check
{
    "marking_code": "код_маркировки",
    "inn": "ИНН",
    "gtin": "GTIN"
}
```

Ответ:
```json
{
    "success": true,
    "data": {
        "user_status": {
            "ok": true,
            "text": "Марка разрешена к продаже, срок годности не истёк"
        },
        "machine_data": {
            "uuid": "unique_id",
            "time": "2024-01-01 12:00:00",
            "permitInfo": {...}
        }
    }
}
```

### Проверка на ККТ

**Постановка в очередь:**
```
POST /api/ecr-mark-check/enqueue
{
    "marking_code": "код_маркировки",
    "inn": "ИНН",
    "gtin": "GTIN"
}
```

Ответ:
```json
{
    "success": true,
    "data": {
        "task_id": "ecr_mark_60f7a123456789",
        "user_status": {
            "ok": true,
            "text": "Задача проверки марки поставлена в очередь"
        },
        "machine_data": {
            "taskId": "ecr_mark_60f7a123456789",
            "taskStatus": "enqueued"
        }
    }
}
```

**Получение результата:**
```
GET /api/ecr-mark-check/result/{taskId}
```

Возможные ответы:

Задача выполняется (HTTP 202):
```json
{
    "success": true,
    "data": {
        "user_status": {
            "ok": false,
            "text": "Проверка марки на ККТ в процессе выполнения"
        },
        "machine_data": {
            "taskStatus": "processing",
            "taskId": "ecr_mark_60f7a123456789"
        }
    }
}
```

Задача завершена успешно (HTTP 200):
```json
{
    "success": true,
    "data": {
        "user_status": {
            "ok": true,
            "text": "Марка корректна и прошла проверку на ККТ"
        },
        "machine_data": {
            "taskStatus": "completed",
            "taskId": "ecr_mark_60f7a123456789",
            "itemInfoCheckResult": {
                "ecrStandAloneFlag": false,
                "imcCheckFlag": true,
                "imcCheckResult": true,
                "imcEstimatedStatusCorrect": true,
                "imcStatusInfo": true
            }
        }
    }
}
```

## Воркер для обработки очереди

Для обработки асинхронных задач используйте `EcrMarkCheckWorker`:

```php
$queue = new EcrMarkCheckQueue();
$worker = new EcrMarkCheckWorker(
    $queue,
    'https://api.markirovka.ru',
    'your_api_key'
);

// Обработка задач
$results = $worker->processQueue();
```

## Структура данных OperationResult

Все результаты возвращаются в едином формате:

```php
class OperationResult {
    public bool $success;
    public ?string $message;
    public ?array $data;
    public ?string $error;
}
```

Структура поля `data`:
```php
[
    'user_status' => [
        'ok' => bool,
        'text' => string  // Сообщение для оператора
    ],
    'machine_data' => [
        // Для permit: uuid, time, permitInfo
        // Для ecr: taskId, taskStatus, itemInfoCheckResult
    ]
]
```

## Принципы

1. **Разделение ответственности:** каждый режим имеет свой интерфейс и use case
2. **Единый формат результата:** все используют OperationResult
3. **Никаких printed_lines:** только бизнесовые данные
4. **Асинхронность для ККТ:** всегда enqueue + getResult
5. **Синхронность для permit:** простой вызов, сразу результат

## Миграция

Старый код можно постепенно мигрировать на новую архитектуру:

1. Заменить `ValidateMarkUseCase` на соответствующие новые use case
2. Обновить контроллеры для использования новых endpoints
3. Настроить воркер для обработки асинхронных задач
4. Удалить старые файлы после полной миграции