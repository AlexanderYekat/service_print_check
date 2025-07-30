<?php
// src/bootstrap.php

require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/domain/service/PrintCheckUseCase.php';
require_once __DIR__ . '/domain/service/ProcessBankPaymentUseCase.php';
require_once __DIR__ . '/infrastructure/bank/GoBankTerminalAdapter.php';
require_once __DIR__ . '/infrastructure/printer/SerialKktAdapter.php';
require_once __DIR__ . '/infrastructure/scale/ScaleDriver.php';
require_once __DIR__ . '/settings_storage/JsonFileSettingsStorage.php';
require_once __DIR__ . '/api/PrintCheckController.php';
require_once __DIR__ . '/api/BankPaymentController.php';
// ... остальные use-case, adapters, controllers

$settingsStorage = new JsonFileSettingsStorage(__DIR__ . '/../config/settings.json');
$config = $settingsStorage->load();
$scaleSettings = $config['scale'] ?? [];
$printerSettings = $config['printer'] ?? [];

$driverClass = $printerSettings['com_class'] ?? 'AddIn.Fptr10';
$comPort     = $printerSettings['com_port']     ?? 'COM0';
$emulation   = $printerSettings['emulation']    ?? false;

$printerAdapter = new SerialKktAdapter($driverClass, $comPort, $emulation);
$printCheckUseCase = new PrintCheckUseCase($printerAdapter);
$printCheckController = new PrintCheckController($printCheckUseCase);
$router->add('/api/print-check', [$printCheckController, 'handle']);


$bankAdapter = new GoBankTerminalAdapter();
$scaleAdapter = new ScaleDriver($scaleSettings['com_port'], $scaleSettings['baud_rate'], $scaleSettings['model'], $scaleSettings['com_class'], $scaleSettings['emulation']);

$GLOBALS['di'] = [
    // Use-case-ы:
    'printCheckUseCase' => new PrintCheckUseCase($printerAdapter, $settingsStorage),
    'bankPaymentUseCase' => new ProcessBankPaymentUseCase($bankAdapter),
    'settingsUseCase' => new SettingsUseCase($settingsStorage),
    'weightUseCase' => new GetWeightUseCase($scaleAdapter),
    'honestSignUseCase' => new SendToHonestSignUseCase($honestSignGateway),

    // Контроллеры:
    'printCheckController' => new PrintCheckController($GLOBALS['di']['printCheckUseCase']),
    'bankPaymentController' => new BankPaymentController($GLOBALS['di']['bankPaymentUseCase']),
    'settingsController' => new SettingsController($GLOBALS['di']['settingsStorage']),
    'weightController' => new WeightController($GLOBALS['di']['weightUseCase']),
    'honestSignController' => new HonestSignController($GLOBALS['di']['honestSignUseCase']),
    'scaleController' => new ScaleController($GLOBALS['di']['scaleUseCase']),
    'getWeightController' => new GetWeightController($GLOBALS['di']['getWeightUseCase']),   
    'closeShiftController' => new CloseShiftController($GLOBALS['di']['closeShiftUseCase']),
    'versionController' => new VersionController($GLOBALS['di']['versionUseCase']),
    'validateMarkController' => new ValidateMarkController($GLOBALS['di']['validateMarkUseCase']),
];


$router->add('/api/get-weight', [$getWeightController, 'handle']);