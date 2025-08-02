<?php

namespace App\Infrastructure\Bank;

use App\Interface\BankTerminalInterface;
use App\Interface\HealthCheckable;
use App\Interface\SettingsStorageInterface;
use App\Domain\Model\OperationResult;
use App\Infrastructure\Logger\LoggerInterface;
use Exception;

/**
 * Адаптер для работы с банковским терминалом через Go-программу
 * 
 * Инкапсулирует всю техническую логику взаимодействия с Go-бинарём:
 * - запись параметров во временные файлы
 * - запуск Go-программы
 * - ожидание и чтение результата
 * - обработка ошибок и таймаутов
 */
class GoBankTerminalAdapter implements BankTerminalInterface, HealthCheckable
{
    private SettingsStorageInterface $settingsStorage;
    private LoggerInterface $logger;
    private string $binaryPath;
    private int $timeout;
    private bool $emulation;

    public function __construct(
        SettingsStorageInterface $settingsStorage, 
        LoggerInterface $logger
    ) {
        $this->settingsStorage = $settingsStorage;
        $this->logger = $logger;
        $this->loadConfiguration();
    }

    /**
     * Загрузить конфигурацию из настроек
     */
    private function loadConfiguration(): void
    {
        $bankConfig = $this->settingsStorage->get('bank', []);
        
        $this->binaryPath = $bankConfig['binary_path'] ?? './bank/mainbeznal.exe';
        $this->timeout = $bankConfig['timeout'] ?? 30;
        $this->emulation = $bankConfig['emulation'] ?? false;
        
        $this->logger->info("Банковский адаптер загружен с настройками: binary_path={$this->binaryPath}, timeout={$this->timeout}, emulation=" . ($this->emulation ? 'true' : 'false'));
    }

    /**
     * {@inheritdoc}
     */
    public function pay(float $amount): \OperationResult
    {
        $this->logger->info("Выполнение оплаты через банковский терминал, сумма: {$amount}");
        return $this->executeOperation('pay', $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function refund(float $amount): \OperationResult
    {
        $this->logger->info("Выполнение возврата через банковский терминал, сумма: {$amount}");
        return $this->executeOperation('return', $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function cancel(float $amount): \OperationResult
    {
        $this->logger->info("Отмена операции в банковском терминале, сумма: {$amount}");
        return $this->executeOperation('cancel', $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function closeShift(): \OperationResult
    {
        $this->logger->info("Закрытие смены банковского терминала");
        return $this->executeOperation('close_shift');
    }

    /**
     * Выполнить операцию с банковским терминалом через Go-программу
     *
     * @param string $operation Тип операции
     * @param float|null $amount Сумма (если требуется)
     * @return OperationResult
     */
    private function executeOperation(string $operation, ?float $amount = null): \OperationResult
    {
        if ($this->emulation) {
            return $this->createEmulationResult($operation, $amount);
        }

        try {
            // Проверяем существование бинаря
            if (!file_exists($this->binaryPath)) {
                throw new Exception("Go-бинарь не найден по пути: {$this->binaryPath}");
            }

            // Создаем временную папку
            $tempDir = $this->createTempDirectory();
            
            // Записываем параметры операции
            $this->writeOperationParameters($tempDir, $operation, $amount);
            
            // Запускаем Go-программу
            $this->executeBinary();
            
            // Ожидаем и читаем результат
            $result = $this->waitForResult($tempDir);
            
            // Очищаем временные файлы
            $this->cleanupTempFiles($tempDir);
            
            return $this->parseResult($result);
            
        } catch (Exception $e) {
            $this->logger->error("Ошибка выполнения банковской операции '{$operation}': " . $e->getMessage());
            return \OperationResult::failure("Ошибка банковского терминала: " . $e->getMessage());
        }
    }

    /**
     * Создать временную папку для файлов обмена с Go-программой
     */
    private function createTempDirectory(): string
    {
        $baseDir = dirname($this->binaryPath);
        $tempDir = $baseDir . DIRECTORY_SEPARATOR . 'temp';
        
        if (!is_dir($tempDir)) {
            if (!mkdir($tempDir, 0777, true)) {
                throw new Exception("Не удалось создать временную папку: {$tempDir}");
            }
            $this->logger->info("Создана временная папка: {$tempDir}");
        }
        
        return $tempDir;
    }

    /**
     * Записать параметры операции во временные файлы
     */
    private function writeOperationParameters(string $tempDir, string $operation, ?float $amount): void
    {
        $operationFile = $tempDir . DIRECTORY_SEPARATOR . 'operation.txt';
        $amountFile = $tempDir . DIRECTORY_SEPARATOR . 'amount.txt';
        $resultFile = $tempDir . DIRECTORY_SEPARATOR . 'result.json';

        // Удаляем старый файл результата
        if (file_exists($resultFile)) {
            unlink($resultFile);
        }

        // Записываем тип операции
        if (file_put_contents($operationFile, $operation) === false) {
            throw new Exception("Не удалось записать тип операции в файл: {$operationFile}");
        }

        // Записываем сумму, если требуется
        $needAmount = in_array($operation, ['pay', 'return', 'cancel']);
        if ($needAmount) {
            if (!is_numeric($amount) || $amount <= 0) {
                throw new Exception("Некорректная сумма для операции {$operation}: {$amount}");
            }
            
            // Конвертируем в копейки для Go-программы
            $amountInCopecks = (int)($amount * 100);
            if (file_put_contents($amountFile, $amountInCopecks) === false) {
                throw new Exception("Не удалось записать сумму в файл: {$amountFile}");
            }
        } else {
            // Удаляем файл суммы для операций, которые её не требуют
            if (file_exists($amountFile)) {
                unlink($amountFile);
            }
        }

        $this->logger->info("Параметры операции записаны: operation={$operation}" . ($needAmount ? ", amount={$amount}" : ""));
    }

    /**
     * Запустить Go-бинарь
     */
    private function executeBinary(): void
    {
        $command = '"' . $this->binaryPath . '"';
        $this->logger->info("Запуск Go-программы: {$command}");
        
        // Запускаем в фоновом режиме для Windows
        $output = shell_exec($command . " 2>&1");
        
        if ($output !== null) {
            $this->logger->info("Вывод Go-программы: " . trim($output));
        }
    }

    /**
     * Ожидать появления файла результата и прочитать его
     */
    private function waitForResult(string $tempDir): array
    {
        $resultFile = $tempDir . DIRECTORY_SEPARATOR . 'result.json';
        $waitTime = 0;

        $this->logger->info("Ожидание файла результата: {$resultFile} (максимум {$this->timeout} секунд)");

        while (!file_exists($resultFile) && $waitTime < $this->timeout) {
            sleep(1);
            $waitTime++;
        }

        if (!file_exists($resultFile)) {
            throw new Exception("Файл результата не появился за {$this->timeout} секунд");
        }

        $this->logger->info("Файл результата найден через {$waitTime} секунд");

        $resultContent = file_get_contents($resultFile);
        if ($resultContent === false) {
            throw new Exception("Не удалось прочитать файл результата");
        }

        // Удаляем BOM, если есть
        $resultContent = preg_replace('/^\xEF\xBB\xBF|\x{FEFF}/u', '', $resultContent);
        
        $this->logger->info("Содержимое файла результата: {$resultContent}");

        $result = json_decode($resultContent, true);
        if ($result === null) {
            throw new Exception("Некорректный JSON в файле результата: {$resultContent}");
        }

        return $result;
    }

    /**
     * Очистить временные файлы
     */
    private function cleanupTempFiles(string $tempDir): void
    {
        $files = ['operation.txt', 'amount.txt', 'result.json'];
        
        foreach ($files as $file) {
            $filePath = $tempDir . DIRECTORY_SEPARATOR . $file;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        $this->logger->info("Временные файлы очищены");
    }

    /**
     * Разобрать результат от Go-программы в OperationResult
     */
    private function parseResult(array $result): \OperationResult
    {
        $success = $result['Success'] ?? false;
        $message = $result['Message'] ?? '';
        $cheque = $result['Cheque'] ?? '';
        $codeReturn = $result['CodeReturn'] ?? 0;

        if ($success) {
            // Обрабатываем слип
            $slip = $this->processSlip($cheque);
            $this->logger->info("Операция выполнена успешно, получен слип из " . count($slip) . " строк");
            return \OperationResult::success($slip);
        } else {
            // Обрабатываем ошибку
            $errorMessage = $message;
            if ($codeReturn > 0) {
                $errorDescription = $this->decodeErrorCode($codeReturn);
                if (!empty($errorDescription)) {
                    $errorMessage .= " (" . $errorDescription . ")";
                }
            }
            
            $this->logger->error("Банковская операция завершилась с ошибкой: {$errorMessage}");
            return \OperationResult::failure($errorMessage);
        }
    }

    /**
     * Обработать слип от банковского терминала
     */
    private function processSlip(string $cheque): array
    {
        if (empty($cheque)) {
            return [];
        }

        $lines = explode("\n", $cheque);
        
        // Фильтруем пустые строки и служебные символы
        $filteredLines = array_filter($lines, function($value) {
            $trimmed = trim($value);
            return $trimmed !== '' && strpos($value, '~S') === false;
        });

        return array_values($filteredLines);
    }

    /**
     * Создать результат эмуляции для тестирования
     */
    private function createEmulationResult(string $operation, ?float $amount): \OperationResult
    {
        $this->logger->info("Эмуляция банковской операции: {$operation}" . ($amount ? " на сумму {$amount}" : ""));
        
        $slip = [
            "=====================================",
            "           ЭМУЛЯЦИЯ БАНКА           ",
            "=====================================",
            "Операция: " . strtoupper($operation),
            $amount ? "Сумма: " . number_format($amount, 2, '.', ' ') . " руб." : "",
            "Время: " . date('d.m.Y H:i:s'),
            "Статус: ОДОБРЕНО",
            "====================================="
        ];

        return \OperationResult::success(array_filter($slip));
    }

    /**
     * Декодировать код ошибки в понятное сообщение
     */
    private function decodeErrorCode(int $resultCode): string
    {
        $errorCodes = [
            99 => "нет связи с банковским терминалом",
            4120 => "нет связи с банковским терминалом", 
            4100 => "нет связи с банком",
            4119 => "нет связи с банком",
            403 => "неверный ПИН-код",
            4455 => "неверный ПИН-код",
            4451 => "недостаточно средств",
            521 => "недостаточно средств",
            253 => "аппаратный сбой",
            2000 => "операция отменена пользователем",
            2002 => "клиент слишком долго вводил ПИН-код",
            4134 => "на терминале давно не закрывали банковскую смену",
            4401 => "нужно позвонить в банк",
            4404 => "получена команда изъять карту",
            4407 => "получена команда изъять карту",
            4141 => "получена команда изъять карту",
            4143 => "получена команда изъять карту",
            5109 => "карта просрочена"
        ];

        return $errorCodes[$resultCode] ?? "неизвестная ошибка (код: {$resultCode})";
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
                    'message' => 'Банковский терминал работает в режиме эмуляции',
                    'details' => ['emulation' => true],
                    'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
                ];
            }

            // Проверяем доступность go-бинаря
            if (!file_exists($this->binaryPath)) {
                return [
                    'status' => 'error',
                    'message' => 'Go-бинарь банковского терминала не найден',
                    'details' => ['binary_path' => $this->binaryPath],
                    'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
                ];
            }

            // Делаем тестовый запрос с минимальной суммой
            $result = $this->pay(0.01);
            
            return [
                'status' => $result->success ? 'ok' : 'warning',
                'message' => $result->success ? 'Банковский терминал доступен' : 'Тестовая операция завершилась ошибкой',
                'details' => [
                    'test_operation' => $result->success,
                    'error_message' => $result->success ? null : $result->message
                ],
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Ошибка при проверке банковского терминала: ' . $e->getMessage(),
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
        return 'bank_terminal';
    }
}
