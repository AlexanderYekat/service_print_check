# ServicePrintCheckPHP - Документация проекта

Этот проект представляет собой PHP-сервис для взаимодействия с кассовыми аппаратами (ККТ) и другими внешними устройствами (банковские терминалы, весы) через COM-объекты на операционной системе Windows. Ниже вы найдете инструкции по установке, использованию API и подробное описание архитектуры.

## Для пользователей

Если вы являетесь конечным пользователем и вам нужно установить и настроить сервис печати чеков, пожалуйста, следуйте нашей пошаговой инструкции:

[Инструкция по установке](docs/installation_instructions.md)

## Для разработчиков

Если вы являетесь разработчиком и хотите интегрировать свой проект с этим сервисом, ознакомьтесь с подробным описанием API и примерами использования:

[Документация API сервиса печати чеков](docs/receipt_service_api_docs.md)

## Обзор Архитектуры

Приложение разделено на несколько ключевых слоев, каждый из которых имеет четко определенные обязанности:

*   **Слой Приложения / Корень Композиции (`atolservice.php`):** Точка входа в приложение, отвечает за инициализацию всех зависимостей (драйверов, сервисов) и маршрутизацию входящих HTTP-запросов.
*   **Слой Представителя (Presenter) (`handlers.php`):** Обрабатывает входящие запросы, делегирует валидацию и бизнес-логику соответствующим слоям, а затем формирует HTTP-ответы. Не содержит бизнес-логики.
*   **Слой Модели (Model) (`CheckService.php`, `kktutils.php`, `models.php`, `validators.php`, `settings.php`, `scaleutils.php`, `bankutils.php`):** Содержит основную бизнес-логику, взаимодействие с драйверами и внешними устройствами, структуры данных, логику валидации и управления настройками.

## Детальное Описание Компонентов

### `atolservice.php` (Слой Приложения / Корень Композиции)

Это главный файл, который запускает серверное приложение. Его основные функции:
*   Загрузка конфигурационных настроек приложения (`settings.php`).
*   Инициализация низкоуровневых драйверов/COM-объектов (`TFptr10Driver`, `SBRFSRV.Server`, `AddIn.Scale8`). Важно отметить, что создание COM-объектов обернуто в `try-catch` блоки, что позволяет приложению продолжать работу, даже если некоторые внешние драйверы (например, для банка или весов) не могут быть инициализированы.
*   Создание экземпляров сервисов (`CheckService`) и презентеров (`Handler`), инжектируя в них необходимые зависимости.
*   Маршрутизация входящих HTTP-запросов к соответствующим методам в `Handler`.

### `handlers.php` (Слой Представителя - Presenter)

Класс `Handler` является Presenter-слоем. Его обязанности:
*   Прием HTTP-запросов от `atolservice.php`.
*   Декодирование входных данных (JSON).
*   Делегирование валидации входных данных классу `Validator`.
*   Вызов соответствующих методов бизнес-логики в `CheckService`.
*   Формирование HTTP-ответов (успех/ошибка) и их отправка клиенту.
*   **Важно:** `Handler` не содержит никакой бизнес-логики и не имеет прямой зависимости от низкоуровневых драйверов (ККТ, банк, весы). Он работает только с `CheckService`.

### `CheckService.php` (Слой Модели - Service)

Класс `CheckService` инкапсулирует всю бизнес-логику, связанную с операциями ККТ, банковскими операциями и весами.
*   Принимает через конструктор инжектированные зависимости: `TFptr10Driver` (для ККТ), а также COM-объекты для банковских операций и весов.
*   Содержит методы для выполнения конкретных операций, таких как `printCheck`, `closeShift`, `cashIn`, `cashOut`, `bankOperation`, `getWeight` и другие.
*   Использует `TFptr10Driver` и другие COM-объекты для взаимодействия с аппаратным обеспечением.

### `kktutils.php` (Слой Модели - Driver Abstraction / Utilities)

Этот файл содержит вспомогательные функции и класс `TFptr10Driver`.
*   **`TFptr10Driver`:** Абстрагирует низкоуровневое взаимодействие с COM-объектом `ATOL.Fptr10`. Он инкапсулирует параметры подключения к ККТ и методы для открытия/закрытия соединения и выполнения базовых операций драйвера.
*   Вспомогательные функции, такие как `kktutils_formatCheckJSON` и `kktutils_connectWithKassa`, поддерживают взаимодействие с ККТ.

### `models.php` (Слой Модели - Data Structures)

Определяет структуры данных (классы `WSMessage`, `WSResponse`, `CheckItem`, `Payment`, `CheckData`, `Settings`), используемые в приложении для обмена информацией.

### `settings.php` (Слой Модели - Configuration)

Содержит класс `Settings`, который отвечает за загрузку и сохранение конфигурационных параметров приложения (например, COM-порты, IP-адреса ККТ).

### `validators.php` (Слой Модели - Validation)

Содержит класс `Validator` с методами для валидации входных данных (например, `validateCheckData`), обеспечивая целостность и корректность получаемых данных.

## Диаграмма Архитектуры

```mermaid
graph TD;
    subgraph "Слой Представления (View)"
        HTTP[HTTP-Запросы] --> atolservice.php;
    end

    subgraph "Слой Приложения / Корень Композиции"
        atolservice.php -->|1. Загружает| Settings[Settings];
        atolservice.php -->|2. Создает с Settings| TFptr10Driver[TFptr10Driver];
        atolservice.php -->|3. Пытается создать| BankCOM[SBRFSRV.Server];
        atolservice.php -->|3. Пытается создать| ScaleCOM[AddIn.Scale8];
        atolservice.php -->|4. Создает с TFptr10Driver,<br/>BankCOM, ScaleCOM| CheckService[CheckService];
        atolservice.php -->|5. Создает с CheckService| Handler[Handler];
        atolservice.php -->|6. Маршрутизирует| Handler;
    end

    subgraph "Слой Представителя (Presenter)"
        Handler -->|7. Валидирует ввод| Validator[Validator];
        Handler -->|8. Вызывает бизнес-логику| CheckService;
    end

    subgraph "Слой Модели (Model)"
        CheckService -->|9. Использует| TFptr10Driver;
        CheckService -->|10. Использует| BankCOM;
        CheckService -->|11. Использует| ScaleCOM;
        CheckService -->|12. Оперирует данными| Models[Models (CheckData, WSResponse etc.)];
    end

    TFptr10Driver -->|Взаимодействует с| Fptr10COM[ATOL.Fptr10 (COM Object)];

    style atolservice.php fill:#f9f,stroke:#333,stroke-width:2px;
    style Handler fill:#bbf,stroke:#333,stroke-width:2px;
    style CheckService fill:#fbb,stroke:#333,stroke-width:2px;
    style TFptr10Driver fill:#ffb,stroke:#333,stroke-width:2px;
    style BankCOM fill:#eef,stroke:#333,stroke-width:2px;
    style ScaleCOM fill:#efe,stroke:#333,stroke-width:2px;
    style Settings fill:#ccc,stroke:#333,stroke-width:2px;
    style Validator fill:#ccc,stroke:#333,stroke-width:2px;
    style Models fill:#ccc,stroke:#333,stroke-width:2px;
    style HTTP fill:#eee,stroke:#333,stroke-width:2px;
    style Fptr10COM fill:#ddd,stroke:#333,stroke-width:2px;
``` 