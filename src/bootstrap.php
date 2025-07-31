<?php
// src/bootstrap.php - Центральный файл конфигурации DI контейнера

// Подключение всех необходимых файлов
require_once __DIR__ . '/constants.php';

// Domain models
require_once __DIR__ . '/domain/model/Check.php';
require_once __DIR__ . '/domain/model/BankResult.php';
require_once __DIR__ . '/domain/model/BankTransaction.php';
require_once __DIR__ . '/domain/model/OperationResult.php';

require_once __DIR__ . '/domain/model/MarkingCode.php';
// HonestSignResult удален - заменен на OperationResult

// Interfaces
require_once __DIR__ . '/interface/PrinterInterface.php';
require_once __DIR__ . '/interface/BankTerminalInterface.php';
require_once __DIR__ . '/interface/ScaleInterface.php';
require_once __DIR__ . '/interface/SettingsStorageInterface.php';
// ValidateMarkGateway удален - заменен на PermitMarkCheckGateway и EcrMarkCheckGateway

// Infrastructure adapters
require_once __DIR__ . '/infrastructure/printer/SerialKktAdapter.php';
require_once __DIR__ . '/infrastructure/printer/FakePrinterAdapter.php';
require_once __DIR__ . '/infrastructure/bank/GoBankTerminalAdapter.php';
require_once __DIR__ . '/infrastructure/scale/SerialScaleAdapter.php';
require_once __DIR__ . '/infrastructure/scale/FakeScaleAdapter.php';
require_once __DIR__ . '/infrastructure/settings_storage/JsonFileSettingsStorage.php';
// HttpHonestSignGateway и HonestSignQueue удалены - заменены новой архитектурой
require_once __DIR__ . '/infrastructure/honest_sign/HttpPermitMarkCheckGateway.php';
require_once __DIR__ . '/infrastructure/honest_sign/QueueEcrMarkCheckGateway.php';
require_once __DIR__ . '/infrastructure/monitoring/HealthChecker.php';

// Use cases
require_once __DIR__ . '/domain/service/PrintCheckUseCase.php';
require_once __DIR__ . '/domain/service/ProcessBankPaymentUseCase.php';
require_once __DIR__ . '/domain/service/GetWeightUseCase.php';
// ValidateMarkUseCase удален - заменен на PermitMarkCheckUseCase и EcrMarkCheckUseCase
require_once __DIR__ . '/infrastructure/queue/EcrMarkCheckQueue.php';
require_once __DIR__ . '/infrastructure/queue/EcrMarkCheckWorker.php';

// Infrastructure
require_once __DIR__ . '/infrastructure/logger/LoggerInterface.php';
require_once __DIR__ . '/infrastructure/logger/FileLogger.php';
require_once __DIR__ . '/infrastructure/logger/ConsoleLogger.php';

// API Layer
require_once __DIR__ . '/api/BaseController.php';
require_once __DIR__ . '/api/request/RequestValidator.php';
require_once __DIR__ . '/api/response/ResponseFormatter.php';

// Controllers
require_once __DIR__ . '/api/PrintCheckController.php';
require_once __DIR__ . '/api/BankPaymentController.php';
require_once __DIR__ . '/api/GetWeightController.php';
require_once __DIR__ . '/api/CloseShiftController.php';
require_once __DIR__ . '/api/VersionController.php';
require_once __DIR__ . '/api/HealthController.php';
require_once __DIR__ . '/api/QueueController.php';

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
$logger = new FileLogger(
    __DIR__ . '/../logs/app.log',
    $config['logging']['level'] ?? 'info'
);

// Создание адаптеров
$printerAdapter = new SerialKktAdapter(
    $config['printer']['com_class'], 
    $config['printer']['com_port'], 
    $config['printer']['emulation']
);

$bankAdapter = new GoBankTerminalAdapter(
    $config['bank']['binary_path'],
    $config['bank']['emulation'],
    $config['bank']['timeout']
);

$scaleAdapter = new SerialScaleAdapter(__DIR__ . '/../config/settings.json');

// Старые компоненты Честного Знака удалены
// Новая архитектура использует отдельные gateway'и для permit и ecr режимов
// которые создаются напрямую в соответствующих контроллерах

// Создание Use Cases
$printCheckUseCase = new PrintCheckUseCase($printerAdapter, $settingsStorage);
$bankPaymentUseCase = new ProcessBankPaymentUseCase($bankAdapter);
$getWeightUseCase = new GetWeightUseCase($scaleAdapter);
// validateMarkUseCase удален - заменен на новую архитектуру проверки марок

// Новая архитектура очереди для ECR проверки марок
$ecrMarkCheckQueue = new EcrMarkCheckQueue();
$ecrMarkCheckWorker = new EcrMarkCheckWorker(
    $ecrMarkCheckQueue,
    $config['api_url'] ?? 'https://api.markirovka.ru', 
    $config['api_key'] ?? ''
);

// Настройка HealthChecker
$healthChecker = new HealthChecker();
$healthChecker->addService('printer', $printerAdapter, 'printer');
$healthChecker->addService('bank', $bankAdapter, 'bank');
$healthChecker->addService('scale', $scaleAdapter, 'scale');
// honest_sign удален из мониторинга - новая архитектура не требует глобального gateway

// Создание контроллеров
$printCheckController = new PrintCheckController($printCheckUseCase, $logger);
$bankPaymentController = new BankPaymentController($bankPaymentUseCase, $logger);
$getWeightController = new GetWeightController($getWeightUseCase);
$closeShiftController = new CloseShiftController($bankPaymentUseCase);
$versionController = new VersionController();
$healthController = new HealthController($healthChecker, $logger);
$queueController = new QueueController($ecrMarkCheckWorker, $ecrMarkCheckQueue);

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
    'close_shift_controller' => $closeShiftController,
    'version_controller' => $versionController,
    'health_controller' => $healthController,
    'queue_controller' => $queueController,
];