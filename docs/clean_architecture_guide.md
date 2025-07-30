# CloudPosBridge - Руководство по чистой архитектуре

## 📋 Обзор

Проект успешно переведен на чистую архитектуру с инфраструктурными паттернами. Все чекпоинты из технического задания выполнены.

## 🏗️ Архитектура

### Слои приложения:

```
src/
├── domain/           # Доменные модели и бизнес-логика
│   ├── model/        # Сущности (Check, BankResult, etc.)
│   └── service/      # Use Cases (PrintCheckUseCase, etc.)
├── interface/        # Интерфейсы (порты)
├── infrastructure/   # Адаптеры внешних систем
│   ├── printer/      # Адаптеры для ККТ
│   ├── bank/         # Адаптеры банковских терминалов
│   ├── scale/        # Адаптеры весов
│   ├── honest_sign/  # Адаптеры Честного Знака
│   ├── queue/        # Система очередей
│   └── monitoring/   # Мониторинг и health-check
├── api/              # REST API контроллеры
├── tests/            # Юнит-тесты
├── bootstrap.php     # DI контейнер
└── routes.php        # Маршрутизация
```

## 🔧 Основные компоненты

### 1. Доменные модели
- **Check** - Чек с товарами и оплатами
- **BankResult** - Результат банковской операции
- **MarkingCode** - Код маркировки для Честного Знака
- **PrintResult** - Результат печати чека
- **WeightResult** - Результат взвешивания

### 2. Use Cases (сценарии)
- **PrintCheckUseCase** - Печать чека
- **ProcessBankPaymentUseCase** - Банковские операции
- **GetWeightUseCase** - Получение веса
- **ValidateMarkUseCase** - Валидация маркировки
- **SendToHonestSignUseCase** - Отправка в Честный Знак

### 3. Адаптеры
- **SerialKktAdapter** - Работа с ККТ через COM
- **GoBankTerminalAdapter** - Работа с банком через Go-бинарь
- **SerialScaleAdapter** - Работа с весами
- **HttpHonestSignGateway** - HTTP API Честного Знака
- **JsonFileSettingsStorage** - Хранение настроек в JSON

### 4. Инфраструктурные компоненты
- **HonestSignQueue** - Очередь для отложенных операций
- **HealthChecker** - Мониторинг состояния сервисов

## 🚀 API Endpoints

### Печать чеков
```http
POST /api/print-check
Content-Type: application/json

{
  "tableData": [{"name": "Товар", "price": 100, "quantity": 1}],
  "cashier": "Кассир",
  "payments": [{"type": "cash", "amount": 100}],
  "type": "sell",
  "taxationSystem": "osn"
}
```

### Банковские операции
```http
POST /api/bank/pay
{"amount": 100.0}

POST /api/bank/refund  
{"amount": 50.0}

POST /api/bank/close-shift
{}
```

### Весы
```http
GET /api/get-weight
```

### Честный Знак
```http
POST /api/honest-sign/validate
{
  "marking_code": "01234567890123456789",
  "inn": "1234567890",
  "gtin": "4607184110117"
}
```

### Мониторинг
```http
GET /api/health
GET /api/queue/status
POST /api/queue/process
```

## ⚙️ Конфигурация

Настройки хранятся в `config/settings.json`:

```json
{
  "printer": {
    "com_class": "AddIn.Fptr10",
    "com_port": "COM1", 
    "emulation": true
  },
  "bank": {
    "binary_path": "./bank/mainbeznal.exe",
    "emulation": true,
    "timeout": 30
  },
  "scale": {
    "com_port": "COM2",
    "emulation": true
  },
  "honest_sign": {
    "api_url": "https://markirovka.nalog.ru/api/v3",
    "api_key": "your_api_key",
    "use_queue": true
  }
}
```

## 🧪 Тестирование

Запуск тестов:
```bash
php test_clean_architecture.php
```

Тесты покрывают:
- ✅ Use Cases
- ✅ Адаптеры  
- ✅ Очередь
- ✅ Мониторинг

## 📊 Мониторинг

### Health Check
Система автоматически проверяет состояние всех интеграций:
- Принтер чеков
- Банковский терминал
- Весы
- API Честного Знака

### Очередь
При недоступности API Честного Знака операции автоматически добавляются в очередь и выполняются при восстановлении связи.

## 🔄 Переключение режимов

В `public/index.php` есть переключатель:
```php
$useCleanArchitecture = true; // false для старой системы
```

Это позволяет постепенно переходить на новую архитектуру без потери функциональности.

## 🎯 Преимущества новой архитектуры

1. **Тестируемость** - Все компоненты покрыты unit-тестами
2. **Расширяемость** - Легко добавлять новые адаптеры
3. **Надежность** - Система очередей и мониторинг
4. **Maintainability** - Четкое разделение ответственности
5. **Flexibility** - Легкое переключение между реализациями

## 🚦 Статус выполнения ТЗ

- ✅ **Чекпоинт 1** - Аудит и проектирование
- ✅ **Чекпоинт 2** - Доменные модели и интерфейсы  
- ✅ **Чекпоинт 3** - Use Cases
- ✅ **Чекпоинт 4** - Инфраструктурные адаптеры
- ✅ **Чекпоинт 5** - Очередь и отложенные операции
- ✅ **Чекпоинт 6** - Мониторинг и health-check
- ✅ **Чекпоинт 7** - Конфигурирование
- ✅ **Чекпоинт 8** - Тестирование
- ✅ **Чекпоинт 9** - Документация

🎉 **Все чекпоинты выполнены!** Система готова к production использованию.