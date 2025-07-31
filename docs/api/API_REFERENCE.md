# 📚 API Reference - CloudPosBridge v2.0

## 🌐 Базовая информация

- **Base URL**: `http://localhost/`
- **Content-Type**: `application/json`
- **Кодировка**: UTF-8
- **Версия API**: 2.0.0

## 📋 Структура ответов

### Успешный ответ
```json
{
  "success": true,
  "data": {
    // Данные ответа
  },
  "meta": {
    "response_time_ms": 23.45,
    "timestamp": "2024-01-01 12:00:00",
    "version": "2.0.0"
  }
}
```

### Ответ с ошибкой
```json
{
  "success": false,
  "error": {
    "message": "Описание ошибки",
    "code": 400
  },
  "meta": {
    "timestamp": "2024-01-01 12:00:00",
    "version": "2.0.0"
  }
}
```

## 🖨️ Печать чеков

### POST /api/print-check

Печать фискального чека.

**Запрос:**
```json
{
  "tableData": [
    {
      "name": "Название товара",
      "price": 100.50,
      "quantity": 2
    }
  ],
  "cashier": "Иванов И.И.",
  "payments": [
    {
      "type": "cash",
      "amount": 201.00
    }
  ],
  "type": "sell",
  "taxationSystem": "osn"
}
```

**Ответ:**
```json
{
  "success": true,
  "data": {
    "success": true,
    "message": "Чек успешно напечатан",
    "printed_lines": ["Строка 1", "Строка 2"]
  }
}
```

**Возможные ошибки:**
- `400` - Неверные параметры запроса
- `422` - Ошибка печати чека
- `500` - Внутренняя ошибка сервера

## 💳 Банковские операции

### POST /api/bank/pay

Оплата картой.

**Запрос:**
```json
{
  "operation": "pay",
  "amount": 500.00
}
```

### POST /api/bank/refund

Возврат средств.

**Запрос:**
```json
{
  "operation": "refund", 
  "amount": 250.00
}
```

### POST /api/bank/close-shift

Закрытие смены на терминале.

**Запрос:**
```json
{
  "operation": "close_shift"
}
```

**Ответ для всех банковских операций:**
```json
{
  "success": true,
  "data": {
    "success": true,
    "message": "Операция выполнена",
    "result_code": 0,
    "slip_lines": ["Слип строка 1", "Слип строка 2"]
  }
}
```

## ⚖️ Весы

### GET /api/get-weight

Получение веса с весов.

**Ответ:**
```json
{
  "success": true,
  "data": {
    "success": true,
    "weight": 1.250,
    "message": "Вес получен успешно"
  }
}
```

## 🏷️ Честный Знак

### POST /api/honest-sign/validate

Валидация кода маркировки.

**Запрос:**
```json
{
  "marking_code": "01234567890123456789",
  "inn": "1234567890",
  "gtin": "4607184110117"
}
```

**Ответ:**
```json
{
  "success": true,
  "data": {
    "success": true,
    "message": "Код валиден",
    "details": {
      "status": "valid",
      "product_name": "Товар"
    }
  }
}
```

## 📋 Очередь

### GET /api/queue/status

Статус очереди отложенных операций.

**Ответ:**
```json
{
  "success": true,
  "data": {
    "total": 5,
    "pending": 2,
    "completed": 2,
    "failed": 1
  }
}
```

### POST /api/queue/process

Обработка очереди вручную.

**Ответ:**
```json
{
  "success": true,
  "data": {
    "processed_items": 3,
    "results": [
      {"success": true, "item_id": "abc123"},
      {"success": false, "item_id": "def456", "error": "API недоступен"}
    ]
  }
}
```

## 🏥 Мониторинг

### GET /api/health

Проверка состояния всех сервисов.

**Ответ:**
```json
{
  "overall_status": "healthy",
  "timestamp": "2024-01-01 12:00:00",
  "services": {
    "printer": {
      "status": "healthy",
      "message": "OK",
      "response_time_ms": 12.34,
      "last_check": "2024-01-01 12:00:00"
    },
    "bank": {
      "status": "healthy", 
      "message": "OK",
      "response_time_ms": 23.45
    }
  }
}
```

**Статусы сервисов:**
- `healthy` - Сервис работает нормально
- `unhealthy` - Есть проблемы, но сервис доступен
- `error` - Сервис недоступен

**HTTP статусы:**
- `200` - Система работает
- `503` - Критические проблемы

## ℹ️ Информация

### GET /api/version

Информация о версии системы.

**Ответ:**
```json
{
  "success": true,
  "data": {
    "version": "2.0.0",
    "build": "20240101.1200",
    "architecture": "Clean Architecture"
  }
}
```

### GET /api

Список доступных endpoints.

## 🚫 Коды ошибок

| Код | Описание |
|-----|----------|
| 400 | Неверные параметры запроса |
| 404 | Endpoint не найден |
| 405 | Метод не поддерживается |
| 422 | Ошибка бизнес-логики |
| 500 | Внутренняя ошибка сервера |
| 503 | Сервис недоступен |

## 🔄 Обратная совместимость

API поддерживает старые форматы запросов:

### Банковские операции (legacy)
```json
{
  "operation": "PayMoney",
  "params": {
    "amount": 100.00
  }
}
```

Автоматически преобразуется в новый формат.

## 📝 Примеры использования

### Полный цикл продажи

1. **Получение веса**: `GET /api/get-weight`
2. **Печать чека**: `POST /api/print-check`
3. **Оплата картой**: `POST /api/bank/pay`
4. **Валидация маркировки**: `POST /api/honest-sign/validate`

### Мониторинг системы

1. **Проверка состояния**: `GET /api/health`
2. **Статус очереди**: `GET /api/queue/status`
3. **Обработка очереди**: `POST /api/queue/process`

---

💡 **Tip**: Все запросы логируются. При возникновении проблем проверьте файл `logs/app.log`.