<?php
// src/bootstrap.php

require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/domain/service/PrintCheckUseCase.php';
require_once __DIR__ . '/domain/service/ProcessBankPaymentUseCase.php';
require_once __DIR__ . '/infrastructure/bank/GoBankTerminalAdapter.php';
require_once __DIR__ . '/infrastructure/printer/YourPrinterAdapter.php';
require_once __DIR__ . '/settings_storage/JsonFileSettingsStorage.php';
require_once __DIR__ . '/api/PrintCheckController.php';
require_once __DIR__ . '/api/BankPaymentController.php';
// ... остальные use-case, adapters, controllers

$settingsStorage = new JsonFileSettingsStorage(__DIR__ . '/../settings/settings.json');
$printerAdapter = new YourPrinterAdapter(/* ... параметры */);
$bankAdapter = new GoBankTerminalAdapter();

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
];
