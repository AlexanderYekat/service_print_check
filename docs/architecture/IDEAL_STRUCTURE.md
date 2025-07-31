# 🏗️ Идеальная структура проекта CloudPosBridge

## 📁 Структура папок

```
service_print_check_php/
├── 📁 src/                          # Основной код приложения
│   ├── 📁 api/                      # API слой (контроллеры)
│   │   ├── 📁 controllers/          # REST контроллеры
│   │   ├── 📁 request/              # Валидаторы запросов
│   │   ├── 📁 response/             # Форматтеры ответов
│   │   └── BaseController.php       # Базовый контроллер
│   │
│   ├── 📁 domain/                   # Доменный слой (бизнес-логика)
│   │   ├── 📁 model/                # Доменные модели
│   │   │   ├── Check.php
│   │   │   ├── BankResult.php
│   │   │   ├── MarkingCode.php
│   │   │   └── ...
│   │   └── 📁 service/              # Use Cases (сценарии)
│   │       ├── PrintCheckUseCase.php
│   │       ├── ProcessBankPaymentUseCase.php
│   │       └── ...
│   │
│   ├── 📁 interface/                # Интерфейсы (порты)
│   │   ├── PrinterInterface.php
│   │   ├── BankTerminalInterface.php
│   │   ├── ValidateMarkGateway.php
│   │   └── ...
│   │
│   ├── 📁 infrastructure/           # Инфраструктурный слой (адаптеры)
│   │   ├── 📁 bank/                 # Банковские адаптеры
│   │   ├── 📁 printer/              # Принтеры чеков
│   │   ├── 📁 scale/                # Весы
│   │   ├── 📁 honest_sign/          # Честный Знак
│   │   ├── 📁 queue/                # Системы очередей
│   │   ├── 📁 settings_storage/     # Хранилища настроек
│   │   ├── 📁 logger/               # Логгеры
│   │   └── 📁 monitoring/           # Мониторинг
│   │
│   ├── 📁 legacy/                   # Legacy код (временно)
│   │   ├── CheckService.php
│   │   ├── handlers.php
│   │   └── ...
│   │
│   ├── bootstrap.php                # DI контейнер
│   └── routes.php                   # Маршрутизация
│
├── 📁 tests/                        # Тесты (вне src!)
│   ├── 📁 unit/                     # Юнит-тесты
│   ├── 📁 integration/              # Интеграционные тесты
│   └── TestRunner.php               # Тест-раннер
│
├── 📁 docs/                         # Документация
│   ├── 📁 architecture/             # Архитектурная документация
│   ├── 📁 api/                      # API документация
│   └── 📁 deployment/               # Инструкции по развертыванию
│
├── 📁 config/                       # Конфигурация
│   ├── settings.json                # Настройки приложения
│   └── ...
│
├── 📁 public/                       # Публичная папка
│   └── index.php                    # Точка входа
│
├── 📁 scripts/                      # Утилиты и скрипты
│   ├── deploy.php
│   └── migrate.php
│
├── 📁 storage/                      # Данные и логи
│   ├── 📁 logs/                     # Логи
│   ├── 📁 cache/                    # Кэш
│   └── 📁 queue/                    # Файлы очередей
│
└── 📁 vendor/                       # Зависимости Composer
```

## 🎯 Принципы организации

### 1. **Разделение по слоям**
- `domain/` - чистая бизнес-логика, независимая от внешних зависимостей
- `interface/` - контракты между слоями
- `infrastructure/` - все внешние зависимости и их адаптеры
- `api/` - тонкий слой для обработки HTTP запросов

### 2. **Направление зависимостей**
```
API → Domain ← Infrastructure
     ↙     ↘
Interface  Interface
```

### 3. **Именование файлов**
- **Классы**: PascalCase (`PrintCheckUseCase.php`)
- **Интерфейсы**: PascalCase + Interface (`PrinterInterface.php`)
- **Конфиги**: snake_case (`settings.json`)
- **Скрипты**: snake_case (`test_runner.php`)

### 4. **Структура API слоя**
```
src/api/
├── BaseController.php              # Базовая функциональность
├── controllers/                    # Специфичные контроллеры
│   ├── PrintCheckController.php
│   ├── BankPaymentController.php
│   └── ...
├── request/                        # Валидация запросов
│   ├── RequestValidator.php
│   └── ...
└── response/                       # Форматирование ответов
    ├── ResponseFormatter.php
    └── ...
```

### 5. **Инфраструктурные компоненты**
```
src/infrastructure/
├── adapter_type/                   # Группировка по типу
│   ├── SpecificAdapter.php
│   ├── FakeAdapter.php            # Для тестов
│   └── MockAdapter.php            # Для разработки
└── ...
```

## 🔧 DI Container структура

```php
$container = [
    // Конфигурация
    'config' => [...],
    
    // Инфраструктура
    'logger' => FileLogger,
    'settings_storage' => JsonFileSettingsStorage,
    
    // Адаптеры
    'printer_adapter' => SerialKktAdapter,
    'bank_adapter' => GoBankTerminalAdapter,
    
    // Use Cases
    'print_check_use_case' => PrintCheckUseCase,
    
    // Контроллеры
    'print_check_controller' => PrintCheckController,
];
```

## 📊 Преимущества такой структуры

### ✅ **Читаемость**
- Любой разработчик за 5 минут понимает архитектуру
- Четкое разделение ответственности
- Легко найти нужный компонент

### ✅ **Тестируемость** 
- Все зависимости инъектируются
- Легко создавать mock'и и заглушки
- Изолированное тестирование слоев

### ✅ **Расширяемость**
- Новые адаптеры добавляются без изменения бизнес-логики
- Легко добавлять новые интеграции
- Простое переключение между реализациями

### ✅ **Поддерживаемость**
- Минимальный технический долг
- Четкие границы модулей
- Простое обновление зависимостей

## 🚀 Миграционный план

1. **Этап 1**: Переместить legacy код в `src/legacy/`
2. **Этап 2**: Разделить API слой на подпапки
3. **Этап 3**: Вынести тесты из `src/`
4. **Этап 4**: Организовать документацию
5. **Этап 5**: Очистить корень проекта
6. **Этап 6**: Постепенно избавляться от legacy

---

*Эта структура обеспечивает максимальную ясность, поддерживаемость и расширяемость проекта.*