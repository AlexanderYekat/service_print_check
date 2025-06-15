<?php
// bankutils.php

class TBankDriver {
    private $bank = null;
    private $logger;
    private $emulation;

    public function __construct(bool $emulation = false, ?Logger $logger = null) {
        $this->emulation = $emulation;
        $this->logger = $logger ?? Logger::getInstance();
    }

    public function getEmulation(): bool {
        return $this->emulation;
    }

    public function Open(): array {
        $this->logger->info("Попытка открытия соединения с банковским терминалом. Эмуляция: " . ($this->emulation ? 'Да' : 'Нет'));

        $actualSuccess = false;
        $actualErrorMessage = "";

        try {
            if ($this->bank === null) {
                $this->bank = new COM("SBRFSRV.Server");
                $this->logger->info("COM-объект SBRFSRV.Server успешно создан.");
            }
            $actualSuccess = true;
        } catch (Exception $e) {
            $actualErrorMessage = "Ошибка открытия соединения с банковским терминалом: " . $e->getMessage();
            $this->logger->error($actualErrorMessage);
            $this->bank = null; // Сбросить объект в случае ошибки
            $actualSuccess = false;
        }

        // Определяем финальный успех на основе эмуляции
        $finalSuccess = $this->emulation ? true : $actualSuccess;
        $finalMessage = $this->emulation ? "" : $actualErrorMessage;

        return [$finalSuccess, $finalMessage];
    }

    public function Close(): void {
        if ($this->bank === null) {
            $this->logger->warning("Попытка закрыть неинициализированный драйвер банковского терминала.");
            return;
        }
        try {
            // Для SBRFSRV.Server нет явного метода Close.
            // Освобождение объекта происходит при завершении скрипта или когда объект перестает быть ссылаемым.
            // Установим $this->bank в null, чтобы явно "закрыть" его с точки зрения нашего класса.
            $this->bank = null;
            $this->logger->info("Соединение с банковским терминалом успешно закрыто (объект освобожден).");
        } catch (Exception $e) {
            $this->logger->error("Ошибка при закрытии соединения с банковским терминалом: " . $e->getMessage());
        }
    }

    private function callBankMethod(string $method, array $params = [], int $nFunCode = 0): array {
        $this->logger->info("Попытка вызова метода {$method} банковского терминала. Эмуляция: " . ($this->emulation ? 'Да' : 'Нет'));

        $actualSuccess = false;
        $actualCheque = "";
        $actualErrorDescription = "";
        $resultCode = -1; // Для хранения кода результата COM-объекта

        // Проверяем, инициализирован ли банковский объект, но не возвращаем сразу. Просто логируем ошибку.
        if ($this->bank === null) {
            $this->logger->error("Драйвер банковского терминала не инициализирован для вызова метода {$method}.");
            // Если не инициализирован, мы все равно продолжаем попытку и затем возвращаем успех в эмуляции.
        } else {
            try {
                $this->bank->Clear();
                foreach ($params as $key => $value) {
                    $this->bank->SParam($key, $value);
                }

                if ($nFunCode !== 0) {
                    $this->logger->info("Вызов NFun с кодом {$nFunCode}");
                    $resultCode = $this->bank->NFun($nFunCode);
                } else {
                    $this->logger->info("Вызов метода {$method}");
                    $resultCode = call_user_func_array([$this->bank, $method], array_values($params));
                }

                if ($resultCode === 0) { // SBRFSRV.Server обычно возвращает 0 при успехе
                    $this->logger->info("Метод {$method} успешно вызван. Код результата: {$resultCode}");
                    $actualSuccess = true;
                    try {
                        $actualCheque = $this->bank->GParamString("Cheque");
                    } catch (Exception $e) {
                        $this->logger->warning("Параметр 'Cheque' не найден или произошла ошибка при его получении: " . $e->getMessage());
                    }
                } else {
                    $this->logger->warning("Метод {$method} вызван с ошибкой. Код результата: {$resultCode}");
                    $actualSuccess = false;
                    try {
                        $actualErrorDescription = $this->bank->GParamString("ResultDescription");
                    } catch (Exception $e) {
                         $this->logger->warning("Параметр 'ResultDescription' не найден или произошла ошибка при его получении: " . $e->getMessage());
                    }
                    $RashivrovkaKodaOshibki = "";
                    if ($resultCode === 99 || $resultCode === 4120) {
                        $RashivrovkaKodaOshibki = "нет связи с банковским терминалом";
                    }
                    if ($resultCode === 403 || $resultCode === 4455) {
                        $RashivrovkaKodaOshibki = "неверний ПИК-код";
                    }
                    if ($resultCode === 4451 || $resultCode === 521) {
                        $RashivrovkaKodaOshibki = "недостаточно средств";
                    }
                    if ($resultCode === 253) {
                        $RashivrovkaKodaOshibki = "аппартаный сбой";
                    }
                    if ($resultCode === 2000) {
                        $RashivrovkaKodaOshibki = "операция отменена пользователем";
                    }
                    if ($resultCode === 2002) {
                        $RashivrovkaKodaOshibki = "клиент слишком долго вводил ПИК-код";
                    }
                    if ($resultCode === 4100 || $resultCode === 4119) {
                        $RashivrovkaKodaOshibki = "нет связи с банком";
                    }
                    if ($resultCode === 4134) {
                        $RashivrovkaKodaOshibki = "на терминале давно не закрывали бакновскую смену";
                    }
                    if ($resultCode === 4401) {
                        $RashivrovkaKodaOshibki = "нужно позвонить в банк";
                    }
                    if ($resultCode === 4404 || $resultCode === 4407 || $resultCode === 4141 || $resultCode === 4143) {
                        $RashivrovkaKodaOshibki = "получена команда изъять карту";
                    }
                    if ($resultCode === 4451 || $resultCode === 5109) {
                        $RashivrovkaKodaOshibki = "карта просрочена";
                    }
                    if ($actualErrorDescription == "") {
                        $actualErrorDescription = "Ошибка при вызове метода {$method} (c кодом операции {$nFunCode}) (код ошибки: {$resultCode}) банковского терминала: {$RashivrovkaKodaOshibki}";
                    }
                    $this->logger->error($actualErrorDescription);
                }
            } catch (Exception $e) {
                $actualErrorDescription = "Исключение при вызове метода {$method} банковского терминала: " . $e->getMessage();
                $this->logger->error($actualErrorDescription);
                $actualSuccess = false;
            } finally {
                if ($this->bank !== null) { // Очищать параметры только если объект был инициализирован
                    $this->bank->Clear();
                }
            }
        }

        // Определяем финальный успех на основе эмуляции
        $finalSuccess = $this->emulation ? true : $actualSuccess;
        $returnResult = null; // This will hold either a string (error) or an array of strings (slip lines)

        if ($finalSuccess) {
            if ($actualCheque != "") {
                $returnResult = explode("\n", $actualCheque);
            } else {
                // Emulation success
                $emulationSlip = "Эмуляция: операция успешна.\nОПЕРАЦИЯ: УСПЕШНО\nСУММА: УКАЗАННАЯ_СУММА РУБ.\nКАРТА: **** **** **** XXXX\nСПАСИБО";
                $returnResult = explode("\n", $emulationSlip);
            }
        } else {
            // For failure
            $returnResult = $actualErrorDescription != "" ? $actualErrorDescription : "Неизвестная ошибка.";
        }

        return [$finalSuccess, $returnResult];
    }

    public function PayMoney(float $amount): array {
        $this->logger->info("Попытка оплаты по безналу. Сумма: {$amount}.");
        return $this->callBankMethod("NFun", ["Amount" => $amount], 4000);
    }

    public function ReturnMoney(float $amount): array {
        $this->logger->info("Попытка возврата по безналу. Сумма: {$amount}.");
        return $this->callBankMethod("NFun", ["Amount" => $amount], 4002);
    }

    public function CloseShiftTerminal(): array {
        $this->logger->info("Попытка закрытия смены терминала.");
        return $this->callBankMethod("NFun", [], 6000);
    }
} 