<?php
// src/bootstrap.php - Центральный файл конфигурации DI контейнера

// Подключение всех необходимых файлов
require_once __DIR__ . '/constants.php';

// Domain models
require_once __DIR__ . '/domain/model/Check.php';
require_once __DIR__ . '/domain/model/BankResult.php';
require_once __DIR__ . '/domain/model/BankTransaction.php';
require_once __DIR__ . '/domain/model/CheckPrintResult.php';
require_once __DIR__ . '/domain/model/WeightResult.php';
require_once __DIR__ . '/domain/model/MarkingCode.php';
require_once __DIR__ . '/domain/model/HonestSignResult.php';

// Interfaces
require_once __DIR__ . '/interface/PrinterInterface.php';
require_once __DIR__ . '/interface/BankTerminalInterface.php';
require_once __DIR__ . '/interface/ScaleInterface.php';
require_once __DIR__ . '/interface/SettingsStorageInterface.php';
require_once __DIR__ . '/interface/ValidateMarkGateway.php';

// Infrastructure adapters
require_once __DIR__ . '/infrastructure/printer/SerialKktAdapter.php';
require_once __DIR__ . '/infrastructure/printer/FakePrinterAdapter.php';
require_once __DIR__ . '/infrastructure/bank/GoBankTerminalAdapter.php';
require_once __DIR__ . '/infrastructure/scale/SerialScaleAdapter.php';
require_once __DIR__ . '/infrastructure/scale/FakeScaleAdapter.php';
require_once __DIR__ . '/infrastructure/settings_storage/JsonFileSettingsStorage.php';
require_once __DIR__ . '/infrastructure/honest_sign/HttpHonestSignGateway.php';
require_once __DIR__ . '/infrastructure/queue/HonestSignQueue.php';
require_once __DIR__ . '/infrastructure/monitoring/HealthChecker.php';

// Use cases
require_once __DIR__ . '/domain/service/PrintCheckUseCase.php';
require_once __DIR__ . '/domain/service/ProcessBankPaymentUseCase.php';
require_once __DIR__ . '/domain/service/GetWeightUseCase.php';
require_once __DIR__ . '/domain/service/ValidateMark.php';
require_once __DIR__ . '/domain/service/SendToHonestSignUseCase.php';

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

$honestSignQueue = new HonestSignQueue();
$honestSignGateway = new HttpHonestSignGateway(
    $config['honest_sign']['api_url'],
    $config['honest_sign']['api_key'],
    $config['honest_sign']['use_queue'],
    $config['honest_sign']['timeout'],
    $honestSignQueue
);

// Создание Use Cases
$printCheckUseCase = new PrintCheckUseCase($printerAdapter, $settingsStorage);
$bankPaymentUseCase = new ProcessBankPaymentUseCase($bankAdapter);
$getWeightUseCase = new GetWeightUseCase($scaleAdapter);
$validateMarkUseCase = new ValidateMarkUseCase($honestSignGateway);
$sendToHonestSignUseCase = new SendToHonestSignUseCase($honestSignGateway);

// Настройка HealthChecker
$healthChecker = new HealthChecker();
$healthChecker->addService('printer', $printerAdapter, 'printer');
$healthChecker->addService('bank', $bankAdapter, 'bank');
$healthChecker->addService('scale', $scaleAdapter, 'scale');
$healthChecker->addService('honest_sign', $honestSignGateway, 'honest_sign');

// Создание контроллеров
$printCheckController = new PrintCheckController($printCheckUseCase);
$bankPaymentController = new BankPaymentController($bankPaymentUseCase);
$getWeightController = new GetWeightController($getWeightUseCase);
$closeShiftController = new CloseShiftController($bankPaymentUseCase);
$versionController = new VersionController();
$healthController = new HealthController($healthChecker);
$queueController = new QueueController($sendToHonestSignUseCase);

// Глобальный DI контейнер
$GLOBALS['di'] = [
    // Settings
    'settings' => $settingsStorage,
    'config' => $config,
    
    // Adapters
    'printer' => $printerAdapter,
    'bank' => $bankAdapter,
    'scale' => $scaleAdapter,
    'honest_sign' => $honestSignGateway,
    'health_checker' => $healthChecker,
    
    // Use Cases
    'print_check_use_case' => $printCheckUseCase,
    'bank_payment_use_case' => $bankPaymentUseCase,
    'get_weight_use_case' => $getWeightUseCase,
    'validate_mark_use_case' => $validateMarkUseCase,
    'send_to_honest_sign_use_case' => $sendToHonestSignUseCase,
    
    // Controllers
    'print_check_controller' => $printCheckController,
    'bank_payment_controller' => $bankPaymentController,
    'get_weight_controller' => $getWeightController,
    'close_shift_controller' => $closeShiftController,
    'version_controller' => $versionController,
    'health_controller' => $healthController,
    'queue_controller' => $queueController,
];