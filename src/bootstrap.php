<?php
// src/bootstrap.php - Центральный файл конфигурации DI контейнера

// Подключение composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Константы (не имеют namespace)
require_once __DIR__ . '/constants.php';

// Используем namespace классы
use App\Domain\Model\{Check, BankTransaction, OperationResult, MarkingCode, CheckItem, Payment};
use App\Interface\{PrinterInterface, BankTerminalInterface, ScaleInterface, SettingsStorageInterface, HealthCheckable};
use App\Domain\Service\{PrintCheckUseCase, ProcessBankPaymentUseCase, GetWeightUseCase, MarkCheckRegistry, MarkMatchingService, PermitMarkCheckUseCase};
use App\Infrastructure\Printer\{SerialKktAdapter, FakePrinterAdapter};
use App\Infrastructure\Bank\GoBankTerminalAdapter;
use App\Infrastructure\Scale\{SerialScaleAdapter, FakeScaleAdapter};
use App\Infrastructure\Monitoring\HealthChecker;
use App\Api\{PrintCheckController, BankPaymentController, GetWeightController, VersionController, HealthController, QueueController, PermitMarkCheckController, EcrMarkCheckController};
use App\Infrastructure\Queue\{EcrMarkCheckQueue, EcrMarkCheckWorker};
use App\Infrastructure\HonestSign\{HttpPermitMarkCheckGateway, QueueEcrMarkCheckGateway};

// Для совместимости с корневым JsonFileSettingsStorage
require_once __DIR__ . '/../JsonFileSettingsStorage.php';

// Infrastructure
require_once __DIR__ . '/infrastructure/logger/LoggerInterface.php';
require_once __DIR__ . '/infrastructure/logger/FileLogger.php';
require_once __DIR__ . '/infrastructure/logger/ConsoleLogger.php';
require_once __DIR__ . '/infrastructure/logger/NullLogger.php';
require_once __DIR__ . '/infrastructure/scale/ScaleDriver.php';

// API Layer
require_once __DIR__ . '/api/BaseController.php';
require_once __DIR__ . '/api/request/RequestValidator.php';
require_once __DIR__ . '/api/response/ResponseFormatter.php';

// Controllers теперь используют автозагрузку с namespace

// Use Cases
require_once __DIR__ . '/domain/service/PermitMarkCheckUseCase.php';
require_once __DIR__ . '/domain/service/EcrMarkCheckUseCase.php';

// HonestSign infrastructure
require_once __DIR__ . '/infrastructure/honest_sign/QueueEcrMarkCheckGateway.php';
require_once __DIR__ . '/infrastructure/honest_sign/HttpPermitMarkCheckGateway.php';

// Инициализация настроек
$settingsStorage = new JsonFileSettingsStorage(__DIR__ . '/../config/settings.json');
$config = $settingsStorage->load();

// Настройки по умолчанию
$defaultConfig = [
    'printer' => [
        'com_class' => 'AddIn.Fptr10',
        'com_port' => 'COM1',
        'emulation' => true
    ],
    'bank' => [
        'binary_path' => __DIR__ . '/../bank/mainbeznal.exe',
        'emulation' => true,
        'timeout' => 30
    ],
    'scale' => [
        'com_port' => 'COM2',
        'baud_rate' => 9600,
        'model' => 'cas',
        'com_class' => 'ScaleClass',
        'emulation' => true
    ],
    'honest_sign' => [
        'api_url' => 'https://markirovka.nalog.ru/api/v3',
        'api_key' => '',
        'use_queue' => true,
        'timeout' => 30
    ]
];

$config = array_merge_recursive($defaultConfig, $config);

// Создание логгера
// В тестовом режиме используем NullLogger для избежания проблем с JSON парсингом
$isTestMode = defined('TESTING_MODE') && TESTING_MODE === true;
$logger = $isTestMode 
    ? new \App\Infrastructure\Logger\NullLogger()
    : new \App\Infrastructure\Logger\FileLogger(
        __DIR__ . '/../logs/app.log',
        $config['logging']['level'] ?? 'info'
    );

// Создание адаптеров с передачей конфигурации
$printerAdapter = new SerialKktAdapter(
    $config['printer']['com_class'], 
    $config['printer']['com_port'], 
    $config['printer']['emulation']
);

$bankAdapter = new \App\Infrastructure\Bank\GoBankTerminalAdapter(
    $settingsStorage,
    $logger
);

$scaleAdapter = new SerialScaleAdapter(__DIR__ . '/../config/settings.json', $logger);

// Старые компоненты Честного Знака удалены
// Новая архитектура использует отдельные gateway'и для permit и ecr режимов
// которые создаются напрямую в соответствующих контроллерах

// Создание Use Cases
// Создаем реестр результатов проверок маркировок и сервис сопоставления
$markCheckRegistry = new MarkCheckRegistry();
$markMatchingService = new MarkMatchingService($markCheckRegistry);
$printCheckUseCase = new PrintCheckUseCase($printerAdapter, $settingsStorage, $markMatchingService);
$bankPaymentUseCase = new ProcessBankPaymentUseCase($bankAdapter, $logger);
$getWeightUseCase = new GetWeightUseCase($scaleAdapter);
// validateMarkUseCase удален - заменен на новую архитектуру проверки марок

// Новая архитектура очереди для ECR проверки марок
$ecrMarkCheckQueue = new EcrMarkCheckQueue();
$ecrMarkCheckWorker = new EcrMarkCheckWorker(
    $ecrMarkCheckQueue,
    $config['api_url'] ?? 'https://api.markirovka.ru', 
    $config['api_key'] ?? ''
);

// Настройка HealthChecker с новым интерфейсом
$healthChecker = new HealthChecker();
$healthChecker->addComponent($printerAdapter);
$healthChecker->addComponent($bankAdapter);
$healthChecker->addComponent($scaleAdapter);

// Создание контроллеров с унифицированными параметрами
$printCheckController = new PrintCheckController($printCheckUseCase, $logger);
$bankPaymentController = new BankPaymentController($bankPaymentUseCase, $logger);
$getWeightController = new GetWeightController($getWeightUseCase, $logger);
$versionController = new VersionController($logger);
$healthController = new HealthController($healthChecker, $logger);
$queueController = new QueueController($ecrMarkCheckWorker, $ecrMarkCheckQueue, $logger);

// Создание новых контроллеров
// Безопасное извлечение конфигурации для избежания проблем с array_merge_recursive
$honestSignConfig = $config['honest_sign'] ?? [];
$apiUrl = is_array($honestSignConfig['api_url'] ?? null) 
    ? ($honestSignConfig['api_url'][0] ?? 'https://api.markirovka.ru')
    : ($honestSignConfig['api_url'] ?? 'https://api.markirovka.ru');
$apiKey = is_array($honestSignConfig['x-api-token'] ?? null)
    ? ($honestSignConfig['x-api-token'][0] ?? '')
    : ($honestSignConfig['x-api-token'] ?? '');

$permitMarkCheckUseCase = new PermitMarkCheckUseCase(
    new HttpPermitMarkCheckGateway($apiUrl, $apiKey),
    $markCheckRegistry
);

$permitMarkCheckController = new PermitMarkCheckController(
    $permitMarkCheckUseCase,
    $settingsStorage,
    $printerAdapter,
    $config,
    $logger
);

$ecrMarkCheckUseCase = new \App\Domain\Service\EcrMarkCheckUseCase(new QueueEcrMarkCheckGateway(), $markCheckRegistry);
$ecrMarkCheckController = new EcrMarkCheckController($ecrMarkCheckUseCase, $logger);

// Глобальный DI контейнер
$GLOBALS['di'] = [
    // Settings
    'settings' => $settingsStorage,
    'config' => $config,
    
    // Adapters
    'printer' => $printerAdapter,
    'bank' => $bankAdapter,
    'scale' => $scaleAdapter,
    // 'honest_sign' удален - используйте новые permit/ecr контроллеры
    'health_checker' => $healthChecker,
    
    // Use Cases
    'print_check_use_case' => $printCheckUseCase,
    'bank_payment_use_case' => $bankPaymentUseCase,
    'get_weight_use_case' => $getWeightUseCase,
    // validate_mark_use_case удален - используйте новые permit/ecr контроллеры
    
    // Queue components
    'ecr_mark_check_queue' => $ecrMarkCheckQueue,
    'ecr_mark_check_worker' => $ecrMarkCheckWorker,
    
    // Controllers
    'print_check_controller' => $printCheckController,
    'bank_payment_controller' => $bankPaymentController,
    'get_weight_controller' => $getWeightController,
    'version_controller' => $versionController,
    'health_controller' => $healthController,
    'queue_controller' => $queueController,
    'permit_mark_check_controller' => $permitMarkCheckController,
    'ecr_mark_check_controller' => $ecrMarkCheckController,
];

/**
 * Получить компонент из DI контейнера
 * 
 * @param string $key Ключ компонента
 * @return mixed Компонент из контейнера
 */
function container(string $key)
{
    if (!isset($GLOBALS['di'][$key])) {
        throw new Exception("Компонент '{$key}' не найден в DI контейнере");
    }
    return $GLOBALS['di'][$key];
}

/**
 * Получить конфигурацию
 * 
 * @param string|null $key Ключ конфигурации (если null - возвращает всю конфигурацию)
 * @param mixed $default Значение по умолчанию
 * @return mixed Значение конфигурации
 */
function config(?string $key = null, $default = null)
{
    $config = container('config');
    
    if ($key === null) {
        return $config;
    }
    
    return $config[$key] ?? $default;
}