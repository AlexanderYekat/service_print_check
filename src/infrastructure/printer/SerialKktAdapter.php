<?php
require_once __DIR__ . '/../../interface/PrinterInterface.php';
require_once __DIR__ . '/../../interface/HealthCheckable.php';
require_once __DIR__ . '/../../domain/model/Check.php';
require_once __DIR__ . '/../../domain/model/OperationResult.php';

class SerialKktAdapter implements PrinterInterface, HealthCheckable
{
    private $fptr = null;
    private $comPort;
    private $ipKkt;
    private $portIpKkt;
    private $ipServKkt;
    private $emulation;

    public function __construct($comPort = 0, $ipKkt = "", $portIpKkt = 0, $ipServKkt = "", $emulation = false)
    {
        $this->comPort = $comPort;
        $this->ipKkt = $ipKkt;
        $this->portIpKkt = $portIpKkt;
        $this->ipServKkt = $ipServKkt;
        $this->emulation = $emulation;
    }

    public function printCheck(Check $check, array $markCheckData = []): OperationResult
    {
        try {
            // 1. Инициализируем драйвер
            $initError = $this->ensureDriverInitialized();
            if ($initError !== null) {
                return OperationResult::failure("Ошибка инициализации драйвера: " . $initError);
            }

            // 2. Открываем соединение с ККТ
            list($isOpened, $connectErrorDesc) = $this->openConnection();
            if (!$isOpened && !$this->emulation) {
                return OperationResult::failure("Ошибка подключения к ККТ: " . $connectErrorDesc);
            }

            try {
                // 3. Форматируем чек в JSON с данными проверок маркировок
                $checkJson = $this->formatCheckToJson($check, $markCheckData);

                // 4. Отправляем команду на печать
                list($success, $responseJson, $commandErrorDesc) = $this->sendCommandToKKT($checkJson);

                if (!$success) {
                    return OperationResult::failure("Ошибка печати чека: " . $commandErrorDesc);
                }

                // 5. Проверяем успешность команды
                if (!$this->isCommandSuccessful($responseJson)) {
                    return OperationResult::failure("ККТ вернула ошибку: " . $responseJson);
                }

                // 6. Формируем успешный ответ
                $printedLines = $this->formatSuccessResponse($responseJson);
                $responseData = json_decode($responseJson, true);
                
                return OperationResult::success("Чек успешно напечатан", [
                    'printed_lines' => $printedLines,
                    'fiscal_data' => $responseData
                ]);

            } finally {
                // 7. Всегда закрываем соединение
                $this->closeConnection();
            }

        } catch (Throwable $e) {
            return OperationResult::failure("Ошибка печати чека: " . $e->getMessage());
        }
    }

    /**
     * Гарантирует что COM драйвер ККТ инициализирован
     */
    private function ensureDriverInitialized(): ?string
    {
        try {
            if ($this->fptr === null) {
                // @phpstan-ignore-next-line COM class доступен только в Windows PHP
                $this->fptr = new COM("AddIn.Fptr10") or die("Не удалось создать объект драйвера ККТ");
            }
            return null;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Открывает соединение с ККТ
     */
    private function openConnection(): array
    {
        if ($this->fptr === null) {
            return [false, "Драйвер не инициализирован"];
        }
        
        if ($this->isConnectionOpened()) {
            return [true, ""];
        }
        
        try {
            // Применяем настройки перед открытием
            $this->applyConnectionSettings();
            $result = $this->fptr->Open();
            if ($result !== 0) {
                $errorDescription = $this->fptr->errorDescription();
                $errorDescription = iconv('Windows-1251', 'UTF-8//IGNORE', $errorDescription ?? '');
                return [false, "Ошибка открытия соединения с ККТ: " . $errorDescription];
            }
            return [$this->isConnectionOpened(), ""];
        } catch (Exception $e) {
            return [false, $e->getMessage()];
        }
    }

    /**
     * Проверяет, открыто ли соединение с ККТ
     */
    private function isConnectionOpened(): bool
    {
        if ($this->fptr === null) {
            return false;
        }
        try {
            return $this->fptr->IsOpened();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Применяет настройки подключения к драйверу
     */
    private function applyConnectionSettings(): void
    {
        $this->fptr->SetSingleSetting($this->fptr->LIBFPTR_SETTING_MODEL, $this->fptr->LIBFPTR_MODEL_ATOL_AUTO);

        if (!empty($this->ipServKkt)) {
            $this->fptr->SetSingleSetting($this->fptr->LIBFPTR_SETTING_REMOTE_SERVER_ADDR, $this->ipServKkt);
        }

        if ($this->comPort == 0) {
            if (!empty($this->ipKkt)) {
                $this->fptr->SetSingleSetting($this->fptr->LIBFPTR_SETTING_PORT, $this->fptr->LIBFPTR_PORT_TCPIP);
                $this->fptr->SetSingleSetting($this->fptr->LIBFPTR_SETTING_IPADDRESS, $this->ipKkt);
                if ($this->portIpKkt != 0) {
                    $this->fptr->SetSingleSetting($this->fptr->LIBFPTR_SETTING_IPPORT, $this->portIpKkt);
                }
            } else {
                $this->fptr->SetSingleSetting($this->fptr->LIBFPTR_SETTING_PORT, $this->fptr->LIBFPTR_PORT_USB);
            }
        } else {
            $sComPorta = "COM" . $this->comPort;
            $this->fptr->SetSingleSetting($this->fptr->LIBFPTR_SETTING_PORT, $this->fptr->LIBFPTR_PORT_COM);
            $this->fptr->SetSingleSetting($this->fptr->LIBFPTR_SETTING_COM_FILE, $sComPorta);
            $this->fptr->SetSingleSetting($this->fptr->LIBFPTR_SETTING_BAUDRATE, $this->fptr->LIBFPTR_PORT_BR_115200);
        }

        $this->fptr->ApplySingleSettings();
    }

    /**
     * Закрывает соединение с ККТ
     */
    private function closeConnection(): void
    {
        if ($this->fptr === null) {
            return;
        }
        try {
            $this->fptr->Close();
        } catch (Exception $e) {
            // Игнорируем ошибки при закрытии
        }
    }

    /**
     * Форматирует чек в JSON для отправки на ККТ
     */
    private function formatCheckToJson(Check $check, array $markCheckData = []): string
    {
        // Формируем позиции чека используя доменную модель
        $checkItems = [];
        foreach ($check->getItems() as $item) {
            $position = [
                "type" => "position",
                "name" => $item->getName(),
                "price" => $item->getPrice(),
                "quantity" => $item->getQuantity(),
                "amount" => $item->getSum(),
                "tax" => [
                    "type" => "none"  // TODO: добавить поддержку налогов в доменную модель
                ]
            ];
            
            // Если у позиции есть маркировка, добавляем данные проверки
            if ($item->hasMarkingCode()) {
                $markingCode = $item->getMarkingCode();
                $cleanCode = $markingCode->getCleanCode();
                
                $position["mark_code"] = $markingCode->getRawCode();
                
                // Ищем данные проверки для этой марки
                if (isset($markCheckData[$cleanCode])) {
                    $checkData = $markCheckData[$cleanCode];
                    
                    // Данные разрешительного режима
                    if (!empty($checkData['permit_check'])) {
                        $permitResult = $checkData['permit_check']['result'];
                        $position["permit_check_status"] = $permitResult->success ? "success" : "failed";
                        if ($permitResult->success && !empty($permitResult->getData())) {
                            $position["permit_check_data"] = $permitResult->getData();
                        }
                    }
                    
                    // Данные проверки ККТ
                    if (!empty($checkData['ecr_check'])) {
                        $ecrResult = $checkData['ecr_check']['result'];
                        $position["ecr_check_status"] = $ecrResult->success ? "success" : "failed";
                        if ($ecrResult->success && !empty($ecrResult->getData())) {
                            $position["ecr_check_data"] = $ecrResult->getData();
                        }
                    }
                    
                    // Общий статус проверки марки
                    $position["mark_fully_checked"] = $checkData['is_fully_checked'];
                }
            }
            
            $checkItems[] = $position;
        }

        // Считаем общую сумму
        $totalAmount = 0.0;
        foreach ($checkItems as $item) {
            $totalAmount += $item['amount'];
        }

        // Формируем оплаты используя доменную модель
        $payments = [];
        foreach ($check->getPayments() as $payment) {
            $payments[] = [
                "type" => $payment->getType(),
                "sum" => $payment->getAmount()
            ];
        }
        
        // Если нет платежей, добавляем наличные на полную сумму
        if (empty($payments)) {
            $payments[] = [
                "type" => "cash",
                "sum" => $totalAmount
            ];
        }

        $checkJSON = [
            "type" => $check->getType(),
            "operator" => [
                "name" => $check->getCashier()
            ],
            "items" => $checkItems,
            "payments" => $payments
        ];

        if (!empty($check->getTaxationSystem())) {
            $checkJSON['taxationType'] = $check->getTaxationSystem();
        }

        return json_encode($checkJSON, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Отправляет команду на ККТ и получает ответ
     */
    private function sendCommandToKKT(string $commandJson): array
    {
        if ($this->fptr === null) {
            return [false, "", "не инициализирован драйвер ккт"];
        }

        // Если эмуляция, возвращаем мок-ответ в зависимости от типа команды
        if ($this->emulation) {
            $decodedCommand = json_decode($commandJson, true);
            $commandType = $decodedCommand['type'] ?? '';
            $mockResponse = '';

            switch ($commandType) {
                case 'cashIn':
                    $mockResponse = '{ "counters" : { "cashSum" : 1345.0 } }';
                    break;
                case 'closeShift':
                    $mockResponse = '{ "fiscalParams" : { "fiscalDocumentDateTime" : "2017-07-25T13:12:00+03:00", "fiscalDocumentNumber" : 69, "fiscalDocumentSign" : "1138986989", "fnNumber" : "9999078900000961", "registrationNumber" : "0000000001002292", "shiftNumber" : 11, "receiptsCount" : 3, "fnsUrl": "www.nalog.gov.ru" }, "warnings": { "notPrinted": false } }';
                    break;
                case 'reportX':
                    $mockResponse = '{ "fiscalParams" : { "fiscalDocumentDateTime" : "2018-03-06T13:52:00+03:00", "fiscalDocumentNumber" : 71, "fiscalDocumentSign" : "1494325660", "fiscalReceiptNumber" : 1, "fnNumber" : "9999078900000961", "registrationNumber" : "0000000001002292", "shiftNumber" : 12, "total" : 390.75, "fnsUrl": "www.nalog.gov.ru" }, "warnings": null }';
                    break;
                case 'nonFiscal':
                    $mockResponse = '{ "success": true, "message": "Банковский слип успешно напечатан (мок)" }';
                    break;
                default: // По умолчанию для других команд, включая printCheck
                    $mockResponse = '{ "fiscalParams" : { "fiscalDocumentDateTime" : "2018-03-06T13:52:00+03:00", "fiscalDocumentNumber" : 71, "fiscalDocumentSign" : "1494325660", "fiscalReceiptNumber" : 1, "fnNumber" : "9999078900000961", "registrationNumber" : "0000000001002292", "shiftNumber" : 12, "total" : 390.75, "fnsUrl": "www.nalog.gov.ru" }, "warnings": null }';
                    break;
            }
            return [true, $mockResponse, ""];
        }

        // Устанавливаем JSON-команду (конвертируем в Windows-1251 для реального ККТ)
        $commandJsonEncoded = iconv('UTF-8', 'Windows-1251', $commandJson ?? '');
        if ($commandJsonEncoded === false) {
            return [false, "", "Ошибка конвертации кодировки"];
        }
        $this->fptr->setParam($this->fptr->LIBFPTR_PARAM_JSON_DATA, $commandJsonEncoded);

        // отправка команды на реальный ККТ
        $result = $this->fptr->processJson();
        if ($result !== 0) {
            $errorDescription = $this->fptr->errorDescription();
            $errorDescription = iconv('Windows-1251', 'UTF-8//IGNORE', $errorDescription ?? '');
            return [false, "", "Ошибка отправки команды на ККТ: {$errorDescription}"];
        }
    
        // Получаем ответ от ККТ
        $jsonAnswer = $this->fptr->GetParamString($this->fptr->LIBFPTR_PARAM_JSON_DATA);
        $jsonAnswer = json_decode($jsonAnswer, true);

        if ($jsonAnswer === null) {
            return [true, "", ""];
        }
        
        return [true, json_encode($jsonAnswer), ""];
    }

    /**
     * Проверяет успешность выполнения команды
     */
    private function isCommandSuccessful(string $resultJson): bool
    {
        // Проверяем наличие слов "ошибка" или "error" в ответе
        $hasError = (mb_stripos($resultJson, 'ошибка') !== false) || (mb_stripos($resultJson, 'error') !== false);
        return !$hasError;
    }

    /**
     * Форматирует успешный ответ для возврата
     */
    private function formatSuccessResponse(string $responseJson): array
    {
        $responseData = json_decode($responseJson, true);
        $printedLines = [];
        
        if ($this->emulation) {
            $printedLines = ["ТЕСТОВЫЙ ЧЕК", "Эмуляция печати", "ОК"];
        } else {
            $printedLines = ["Чек успешно напечатан"];
            
            // Добавляем фискальные данные, если есть
            if (isset($responseData['fiscalParams'])) {
                $fiscalParams = $responseData['fiscalParams'];
                if (isset($fiscalParams['fiscalDocumentNumber'])) {
                    $printedLines[] = "Фискальный документ: " . $fiscalParams['fiscalDocumentNumber'];
                }
                if (isset($fiscalParams['total'])) {
                    $printedLines[] = "Сумма: " . $fiscalParams['total'] . " руб.";
                }
            }
        }

        return $printedLines;
    }

    // ===============================================
    // ДОПОЛНИТЕЛЬНЫЕ МЕТОДЫ ДЛЯ РАБОТЫ С ККТ
    // ===============================================

    /**
     * Печать X-отчета
     */
    public function printXReport(string $cashier): OperationResult
    {
        $initError = $this->ensureDriverInitialized();
        if ($initError !== null) {
            return OperationResult::failure("Ошибка инициализации драйвера: " . $initError);
        }

        $XReportJson = json_encode([
            "type" => "reportX",
            "operator" => [
                "name" => $cashier
            ]
        ], JSON_UNESCAPED_UNICODE);

        list($success, $responseJson, $commandErrorDesc) = $this->sendCommandToKKT($XReportJson);

        if (!$success) {
            return OperationResult::failure($commandErrorDesc);
        }

        $responseData = json_decode($responseJson, true);
        return OperationResult::success("X-отчет успешно напечатан", $responseData);
    }

    /**
     * Печать нефискального слипа
     */
    public function printSlip(array $listOfLines): OperationResult
    {
        $initError = $this->ensureDriverInitialized();
        if ($initError !== null) {
            return OperationResult::failure("Ошибка инициализации драйвера: " . $initError);
        }

        $nonFiscalJson = [
            "type" => "nonFiscal",
            "items" => []
        ];

        foreach ($listOfLines as $line) {
            // Проверяем на пустую строку, включая строки только с пробелами
            if (empty(trim($line))) {
                continue;
            }
            $nonFiscalJson["items"][] = [
                "type" => "text",
                "text" => $line,
                "alignment" => "left" //center
            ];
        }

        $jsonCommand = json_encode($nonFiscalJson, JSON_UNESCAPED_UNICODE);
        list($success, $responseJson, $commandErrorDesc) = $this->sendCommandToKKT($jsonCommand);

        if (!$success) {
            return OperationResult::failure($commandErrorDesc);
        }

        $responseData = json_decode($responseJson, true);
        return OperationResult::success("Слип успешно напечатан", $responseData);
    }

    /**
     * Внесение денег в кассу
     */
    public function cashIn(float $amount, string $operatorName, string $operatorVatin = ""): OperationResult
    {
        $initError = $this->ensureDriverInitialized();
        if ($initError !== null) {
            return OperationResult::failure("Ошибка инициализации драйвера: " . $initError);
        }

        $cashInJson = [
            "type" => "cashIn",
            "operator" => [
                "name" => $operatorName,
            ],
            "cashSum" => $amount
        ];

        // Добавляем Vatin, если он предоставлен
        if (!empty($operatorVatin)) {
            $cashInJson["operator"]["vatin"] = $operatorVatin;
        }

        $jsonCommand = json_encode($cashInJson, JSON_UNESCAPED_UNICODE);
        list($success, $responseJson, $commandErrorDesc) = $this->sendCommandToKKT($jsonCommand);

        if (!$success) {
            return OperationResult::failure($commandErrorDesc);
        }

        $responseData = json_decode($responseJson, true);
        return OperationResult::success("Внесение денег выполнено", $responseData);
    }

    /**
     * Изъятие денег из кассы
     */
    public function cashOut(float $amount, string $operatorName, string $operatorVatin = ""): OperationResult
    {
        $initError = $this->ensureDriverInitialized();
        if ($initError !== null) {
            return OperationResult::failure("Ошибка инициализации драйвера: " . $initError);
        }

        $cashOutJson = [
            "type" => "cashOut",
            "operator" => [
                "name" => $operatorName,
            ],
            "cashSum" => $amount
        ];

        // Добавляем Vatin, если он предоставлен
        if (!empty($operatorVatin)) {
            $cashOutJson["operator"]["vatin"] = $operatorVatin;
        }

        $jsonCommand = json_encode($cashOutJson, JSON_UNESCAPED_UNICODE);
        list($success, $responseJson, $commandErrorDesc) = $this->sendCommandToKKT($jsonCommand);

        if (!$success) {
            return OperationResult::failure($commandErrorDesc);
        }

        $responseData = json_decode($responseJson, true);
        return OperationResult::success("Изъятие денег выполнено", $responseData);
    }

    /**
     * Закрытие смены
     */
    public function closeShift(string $cashier): OperationResult
    {
        $initError = $this->ensureDriverInitialized();
        if ($initError !== null) {
            return OperationResult::failure("Ошибка инициализации драйвера: " . $initError);
        }

        $closeShiftJson = json_encode([
            "type" => "closeShift",
            "operator" => [
                "name" => $cashier
            ]
        ], JSON_UNESCAPED_UNICODE);

        list($success, $responseJson, $commandErrorDesc) = $this->sendCommandToKKT($closeShiftJson);

        if (!$success) {
            return OperationResult::failure($commandErrorDesc);
        }

        $responseData = json_decode($responseJson, true);
        return OperationResult::success("Смена закрыта", $responseData);
    }

    /**
     * Проверяет открыта ли смена
     */
    public function isShiftOpened(): OperationResult
    {
        $initError = $this->ensureDriverInitialized();
        if ($initError !== null) {
            return OperationResult::failure("Ошибка инициализации драйвера: " . $initError);
        }

        // Если эмуляция - возвращаем true (смена всегда "открыта")
        if ($this->emulation) {
            return OperationResult::success("Смена открыта (эмуляция)", [
                'shift_opened' => true,
                'shift_state' => 1, // 1 = LIBFPTR_SS_OPENED
                'connection_type' => $this->getTypeConnection()
            ]);
        }

        list($isOpened, $connectErrorDesc) = $this->openConnection();
        if (!$isOpened) {
            return OperationResult::failure("Ошибка подключения к ККТ: {$this->getTypeConnection()} (Код: {$connectErrorDesc})");
        }

        $result = -1;
        $shiftOpened = false;
        $commandErrorDesc = "";
        
        try {
            $this->fptr->SetParam($this->fptr->LIBFPTR_PARAM_DATA_TYPE, $this->fptr->LIBFPTR_DT_SHIFT_STATE);
            $result = $this->fptr->QueryData();            
            if ($result !== 0) {
                $errorDescription = $this->fptr->errorDescription();
                $commandErrorDesc = iconv('Windows-1251', 'UTF-8//IGNORE', $errorDescription ?? '');
            }
            $result = $this->fptr->GetParamInt($this->fptr->LIBFPTR_PARAM_SHIFT_STATE);
            
            $shiftOpened = ($result === 1 || $result === 2); //LIBFPTR_SS_OPENED = 1, LIBFPTR_SS_EXCHANGE = 2
        } catch (Exception $e) {
            $commandErrorDesc = $e->getMessage();
        } finally {
            $this->closeConnection();
        }
        
        if (!empty($commandErrorDesc)) {
            return OperationResult::failure($commandErrorDesc, [
                'shift_opened' => $shiftOpened,
                'shift_state' => $result
            ]);
        }

        $message = $shiftOpened ? "Смена открыта" : "Смена закрыта";
        return OperationResult::success($message, [
            'shift_opened' => $shiftOpened,
            'shift_state' => $result,
            'connection_type' => $this->getTypeConnection()
        ]);
    }

    /**
     * Получение статуса смены
     */
    public function getShiftStatus(): OperationResult
    {
        $initError = $this->ensureDriverInitialized();
        if ($initError !== null) {
            return OperationResult::failure("Ошибка инициализации драйвера: " . $initError);
        }

        $command = [
            "type" => "getShiftStatus"
        ];

        $jsonCommand = json_encode($command, JSON_UNESCAPED_UNICODE);
        list($success, $responseJson, $commandErrorDesc) = $this->sendCommandToKKT($jsonCommand);

        if (!$success) {
            return OperationResult::failure($commandErrorDesc);
        }

        $responseData = json_decode($responseJson, true);
        return OperationResult::success("Статус смены получен", $responseData);
    }

    /**
     * Начинает проверку кода маркировки
     */
    public function beginMarkingCodeValidation(string $imc): OperationResult
    {
        $initError = $this->ensureDriverInitialized();
        if ($initError !== null) {
            return OperationResult::failure("Ошибка инициализации драйвера: " . $initError);
        }

        $command = [
            "type" => "beginMarkingCodeValidation",
            "params" => [
                "imcType" => "auto",
                "imc" => $imc,
                "itemEstimatedStatus" => "itemPieceSold",
                "imcModeProcessing" => 0
            ]
        ];

        $jsonCommand = json_encode($command, JSON_UNESCAPED_UNICODE);
        list($success, $responseJson, $commandErrorDesc) = $this->sendCommandToKKT($jsonCommand);

        if (!$success) {
            return OperationResult::failure($commandErrorDesc);
        }

        $responseData = json_decode($responseJson, true);
        return OperationResult::success("Проверка кода маркировки начата", $responseData);
    }

    /**
     * Проверяет статус проверки кода маркировки
     */
    public function checkMarkingCodeValidation(): OperationResult
    {
        $initError = $this->ensureDriverInitialized();
        if ($initError !== null) {
            return OperationResult::failure("Ошибка инициализации драйвера: " . $initError);
        }

        $command = [
            "type" => "getMarkingCodeValidationStatus"
        ];

        $jsonCommand = json_encode($command, JSON_UNESCAPED_UNICODE);
        list($success, $responseJson, $commandErrorDesc) = $this->sendCommandToKKT($jsonCommand);

        if (!$success) {
            return OperationResult::failure($commandErrorDesc);
        }

        $responseData = json_decode($responseJson, true);
        return OperationResult::success("Статус проверки маркировки", $responseData);
    }

    /**
     * Принимает код маркировки
     */
    public function acceptMarkingCode(): OperationResult
    {
        $initError = $this->ensureDriverInitialized();
        if ($initError !== null) {
            return OperationResult::failure("Ошибка инициализации драйвера: " . $initError);
        }

        $command = [
            "type" => "acceptMarkingCode"
        ];

        $jsonCommand = json_encode($command, JSON_UNESCAPED_UNICODE);
        list($success, $responseJson, $commandErrorDesc) = $this->sendCommandToKKT($jsonCommand);

        if (!$success) {
            return OperationResult::failure($commandErrorDesc);
        }

        $responseData = json_decode($responseJson, true);
        return OperationResult::success("Код маркировки принят", $responseData);
    }

    /**
     * Очищает результат валидации кода маркировки
     */
    public function clearMarkingCodeValidationResult(): OperationResult
    {
        $initError = $this->ensureDriverInitialized();
        if ($initError !== null) {
            return OperationResult::failure("Ошибка инициализации драйвера: " . $initError);
        }

        $command = [
            "type" => "clearMarkingCodeValidationResult"
        ];

        $jsonCommand = json_encode($command, JSON_UNESCAPED_UNICODE);
        list($success, $responseJson, $commandErrorDesc) = $this->sendCommandToKKT($jsonCommand);

        if (!$success) {
            return OperationResult::failure($commandErrorDesc);
        }

        $responseData = json_decode($responseJson, true);
        return OperationResult::success("Результат валидации очищен", $responseData);
    }

    /**
     * Отменяет текущий чек
     */
    public function cancelReceipt(): OperationResult
    {
        $initError = $this->ensureDriverInitialized();
        if ($initError !== null) {
            return OperationResult::failure("Ошибка инициализации драйвера: " . $initError);
        }

        // Если эмуляция - возвращаем успех
        if ($this->emulation) {
            return OperationResult::success("Чек отменен (эмуляция)");
        }

        $result = $this->fptr->CancelReceipt();
        
        if ($result !== 0) {
            $errorDescription = $this->fptr->errorDescription();
            $commandErrorDesc = iconv('Windows-1251', 'UTF-8//IGNORE', $errorDescription ?? '');
            return OperationResult::failure("Ошибка отмены чека: " . $commandErrorDesc);
        }
        
        return OperationResult::success("Чек успешно отменен");
    }

    /**
     * Получает версию драйвера
     */
    public function getVersion(): string
    {
        $initError = $this->ensureDriverInitialized();
        if ($initError !== null) {
            return "";
        }

        // Если эмуляция - возвращаем версию эмулятора
        if ($this->emulation) {
            return "Эмулятор v1.0";
        }

        try {
            return $this->fptr->Version();
        } catch (Exception $e) {
            return "";
        }
    }

    /**
     * Получает информацию о типе подключения
     */
    public function getTypeConnection(): string
    {
        $typeConnect = "";

        if (!empty($this->ipServKkt)) {
            $typeConnect = "через сервер ККТ по IP {$this->ipServKkt}";
        }

        if ($this->comPort == 0) {
            if (!empty($this->ipKkt)) {
                $typeConnect .= " по IP {$this->ipKkt} ККТ на порт {$this->portIpKkt}";
            } else {
                $typeConnect .= " по USB";
            }
        } else {
            $sComPorta = "COM" . $this->comPort;
            $typeConnect .= " по COM порту {$sComPorta}";
        }

        return $typeConnect;
    }

    /**
     * Получает объект драйвера (для расширенного использования)
     */
    public function getFptrObject()
    {
        $this->ensureDriverInitialized();
        return $this->fptr;
    }

    // ===============================================
    // ГЕТТЕРЫ ДЛЯ ПАРАМЕТРОВ
    // ===============================================

    public function getComPort() { return $this->comPort; }
    public function getIpKkt() { return $this->ipKkt; }
    public function getPortIpKkt() { return $this->portIpKkt; }
    public function getIpServKkt() { return $this->ipServKkt; }
    public function getEmulation() { return $this->emulation; }

    /**
     * Получает номер фискального накопителя с устройства ККТ
     * @return string Номер фискального накопителя
     * @throws Exception Если не удалось получить номер ФН
     */
    public function readFiscalDriveNumberFromDevice(): string 
    {
        try {
            // 1. Инициализируем драйвер
            $initError = $this->ensureDriverInitialized();
            if ($initError !== null) {
                throw new Exception("Ошибка инициализации драйвера: " . $initError);
            }

            // 2. Открываем соединение с ККТ
            list($isOpened, $connectErrorDesc) = $this->openConnection();
            if (!$isOpened && !$this->emulation) {
                throw new Exception("Ошибка подключения к ККТ: " . $connectErrorDesc);
            }

            try {
                // 3. Если режим эмуляции, возвращаем тестовый номер ФН
                if ($this->emulation) {
                    return "9999078900000961"; // Тестовый номер из mock-данных
                }

                // 4. Формируем команду запроса состояния ККТ
                $statusCommand = json_encode([
                    "type" => "getDeviceStatus"
                ]);

                // 5. Отправляем команду на ККТ
                list($success, $responseJson, $commandErrorDesc) = $this->sendCommandToKKT($statusCommand);

                if (!$success) {
                    throw new Exception("Ошибка получения статуса ККТ: " . $commandErrorDesc);
                }

                // 6. Парсим ответ
                $response = json_decode($responseJson, true);
                if ($response === null) {
                    throw new Exception("Некорректный ответ от ККТ при запросе статуса");
                }

                // 7. Извлекаем номер ФН из ответа
                $fnNumber = null;
                if (isset($response['fiscalParams']['fnNumber'])) {
                    $fnNumber = $response['fiscalParams']['fnNumber'];
                } elseif (isset($response['status']['fnNumber'])) {
                    $fnNumber = $response['status']['fnNumber'];
                } elseif (isset($response['fnNumber'])) {
                    $fnNumber = $response['fnNumber'];
                }

                if (empty($fnNumber)) {
                    throw new Exception("Номер фискального накопителя не найден в ответе ККТ");
                }

                return (string)$fnNumber;

            } finally {
                // 8. Закрываем соединение
                $this->closeConnection();
            }

        } catch (Exception $e) {
            throw new Exception("Не удалось получить номер фискального накопителя: " . $e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function checkHealth(): array
    {
        $startTime = microtime(true);
        
        try {
            if ($this->emulation) {
                return [
                    'status' => 'ok',
                    'message' => 'ККТ принтер работает в режиме эмуляции',
                    'details' => ['emulation' => true],
                    'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
                ];
            }

            // Инициализируем драйвер
            $initError = $this->ensureDriverInitialized();
            if ($initError !== null) {
                return [
                    'status' => 'error',
                    'message' => 'Ошибка инициализации драйвера ККТ: ' . $initError,
                    'details' => ['init_error' => $initError],
                    'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
                ];
            }

            // Пытаемся открыть соединение
            list($isOpened, $connectErrorDesc) = $this->openConnection();
            if (!$isOpened) {
                return [
                    'status' => 'warning',
                    'message' => 'Не удается подключиться к ККТ: ' . $connectErrorDesc,
                    'details' => ['connection_error' => $connectErrorDesc],
                    'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
                ];
            }

            // Закрываем соединение после успешного подключения
            $this->closeConnection();

            return [
                'status' => 'ok',
                'message' => 'ККТ принтер доступен и готов к работе',
                'details' => ['connection_test' => 'success'],
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Ошибка при проверке ККТ принтера: ' . $e->getMessage(),
                'details' => ['exception' => get_class($e)],
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getComponentName(): string
    {
        return 'kkt_printer';
    }
}
