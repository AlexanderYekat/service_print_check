# CloudPosBridge

🏪 **Система интеграции POS-терминала с различными устройствами и сервисами**

CloudPosBridge обеспечивает единообразное API для работы с ККТ, банковскими терминалами, весами и системой маркировки "Честный Знак".

## 🚀 Возможности

- ✅ **Печать чеков** через ККТ различных моделей
- 💳 **Банковские операции** (оплата, возврат, отмена)
- ⚖️ **Работа с весами** для товаров на развес
- 🏷️ **Проверка марок** "Честный Знак" (синхронно и асинхронно)
- 📊 **Мониторинг состояния** всех подключенных устройств
- 🔄 **Очереди задач** для асинхронной обработки
- 🧪 **Комплексное тестирование** unit и integration тестов

## 📋 Требования

- **PHP 7.4+** с модулями: json, curl, com_dotnet (для Windows)
- **Composer** для управления зависимостями
- **Git** для управления версиями
- **Веб-сервер** (Apache/Nginx/IIS)

## ⚡ Быстрый старт

### 1. Клонирование и установка

```bash
# Клонируем репозиторий
git clone <repository-url> CloudPosBridge
cd CloudPosBridge

# Быстрая установка (Linux/macOS)
./scripts/quick_deploy.sh

# Быстрая установка (Windows)
.\scripts\quick_deploy.ps1
```

### 2. Настройка

Отредактируйте файл `config/settings.json`:

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
    "api_url": "https://api.markirovka.ru",
    "x-api-token": "your-token-here",
    "timeout": 30
  }
}
```

### 3. Проверка работы

```bash
# Проверка состояния всех компонентов
curl http://localhost/api/health

# Запуск тестов
php tests/run_all_tests.php
```

## 🏗️ Архитектура

CloudPosBridge построен по принципам **Clean Architecture**:

```
src/
├── api/              # Контроллеры HTTP API
├── domain/           # Бизнес-логика и модели
│   ├── model/        # Доменные модели
│   └── service/      # Use Cases (сценарии использования)
├── infrastructure/   # Адаптеры для внешних систем
│   ├── bank/         # Банковские терминалы
│   ├── printer/      # ККТ принтеры
│   ├── scale/        # Весы
│   ├── honest_sign/  # API Честного Знака
│   └── monitoring/   # Мониторинг системы
└── interface/        # Интерфейсы (contracts)
```

## 🌐 API Endpoints

### Основные операции

- `POST /api/print-check` - Печать чека
- `POST /api/bank-payment` - Банковские операции
- `GET /api/weight` - Получение веса с весов
- `GET /api/health` - Состояние системы
- `GET /api/version` - Версия приложения

### Проверка марок

- `POST /api/permit-mark-check` - Синхронная проверка марки
- `POST /api/ecr-mark-check/enqueue` - Асинхронная проверка (постановка в очередь)
- `GET /api/ecr-mark-check/result/{taskId}` - Получение результата проверки

### Очереди

- `GET /api/queue/status` - Статус очереди задач
- `POST /api/queue/process` - Обработка очереди

## 🧪 Тестирование

```bash
# Запуск всех тестов
php tests/run_all_tests.php

# Запуск только unit тестов
php tests/unit/VersionControllerTest.php

# Запуск интеграционных тестов
php tests/integration/test_ideal_architecture.php
```

## 🚀 Деплой

### Автоматический деплой

```bash
# Полный автоматический деплой с проверками
php scripts/deploy.php
```

### Быстрый деплой

```bash
# Linux/macOS
./scripts/quick_deploy.sh

# Windows
.\scripts\quick_deploy.ps1
```

Подробнее о деплое: [scripts/README.md](scripts/README.md)

## 📁 Структура проекта

```
CloudPosBridge/
├── 📁 config/          # Конфигурационные файлы
├── 📁 docs/            # Документация
├── 📁 logs/            # Логи приложения
├── 📁 public/          # Веб-доступная папка
├── 📁 scripts/         # Скрипты деплоя и утилиты
├── 📁 src/             # Исходный код
├── 📁 tests/           # Тесты (unit, integration)
├── 📁 vendor/          # Зависимости Composer
├── 📄 composer.json    # Конфигурация Composer
└── 📄 README.md        # Этот файл
```

## 🔧 Разработка

### Принципы архитектуры

1. **Clean Architecture** - четкое разделение слоев
2. **SOLID** - следование принципам ООП
3. **DRY** - отсутствие дублирования кода
4. **KISS** - простота и понятность
5. **Fail Fast** - раннее обнаружение ошибок

### Добавление нового функционала

1. Создайте доменную модель в `src/domain/model/`
2. Реализуйте Use Case в `src/domain/service/`
3. Создайте адаптер в `src/infrastructure/`
4. Добавьте контроллер в `src/api/`
5. Напишите тесты в `tests/`

## 📊 Мониторинг

### Health Check

Эндпоинт `/api/health` возвращает детальную информацию о состоянии всех компонентов:

```json
{
  "overall_status": "ok",
  "timestamp": "2024-01-20 15:30:45",
  "components_count": 3,
  "components": {
    "bank_terminal": {
      "status": "ok",
      "message": "Банковский терминал доступен",
      "response_time_ms": 125.5
    },
    "kkt_printer": {
      "status": "ok", 
      "message": "ККТ принтер готов к работе",
      "response_time_ms": 89.2
    },
    "scales": {
      "status": "warning",
      "message": "Весы работают в режиме эмуляции",
      "response_time_ms": 12.1
    }
  }
}
```

## 📚 Документация

- [API Reference](docs/api/API_REFERENCE.md) - подробное описание API
- [Installation Guide](docs/installation_instructions.md) - руководство по установке
- [Architecture Guide](docs/clean_architecture_guide.md) - описание архитектуры
- [Deployment Scripts](scripts/README.md) - автоматизация деплоя

---

⭐ **CloudPosBridge** - надежное решение для интеграции POS-систем!