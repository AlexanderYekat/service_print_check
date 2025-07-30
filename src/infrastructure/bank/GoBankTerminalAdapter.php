<?php

require_once __DIR__ . '/../../interface/BankTerminalInterface.php';
require_once __DIR__ . '/../../domain/model/BankResult.php';

class GoBankTerminalAdapter implements BankTerminalInterface 
{
    private string $goBinaryPath;
    private bool $emulation;
    private int $timeout;

    public function __construct(string $goBinaryPath, bool $emulation = false, int $timeout = 30)
    {
        $this->goBinaryPath = $goBinaryPath;
        $this->emulation = $emulation;
        $this->timeout = $timeout;
    }

    public function pay(float $amount): BankResult 
    {
        if ($this->emulation) {
            return new BankResult(
                true, 
                "Эмуляция оплаты на сумму {$amount}", 
                ["ЭМУЛЯЦИЯ БАНКОВСКОГО СЛИПА", "ОПЛАТА: {$amount} руб", "УСПЕШНО"], 
                0
            );
        }

        try {
            $result = $this->executeBankOperation('pay', ['amount' => $amount]);
            return $this->parseBankResult($result);
        } catch (Exception $e) {
            return new BankResult(false, "Ошибка оплаты: " . $e->getMessage(), null, -1);
        }
    }

    public function refund(float $amount): BankResult 
    {
        if ($this->emulation) {
            return new BankResult(
                true, 
                "Эмуляция возврата на сумму {$amount}", 
                ["ЭМУЛЯЦИЯ БАНКОВСКОГО СЛИПА", "ВОЗВРАТ: {$amount} руб", "УСПЕШНО"], 
                0
            );
        }

        try {
            $result = $this->executeBankOperation('refund', ['amount' => $amount]);
            return $this->parseBankResult($result);
        } catch (Exception $e) {
            return new BankResult(false, "Ошибка возврата: " . $e->getMessage(), null, -1);
        }
    }

    public function closeShift(): BankResult 
    {
        if ($this->emulation) {
            return new BankResult(
                true, 
                "Эмуляция закрытия смены", 
                ["ЭМУЛЯЦИЯ БАНКОВСКОГО СЛИПА", "ЗАКРЫТИЕ СМЕНЫ", "УСПЕШНО"], 
                0
            );
        }

        try {
            $result = $this->executeBankOperation('close_shift', []);
            return $this->parseBankResult($result);
        } catch (Exception $e) {
            return new BankResult(false, "Ошибка закрытия смены: " . $e->getMessage(), null, -1);
        }
    }

    private function executeBankOperation(string $operation, array $params): array
    {
        $command = $this->buildCommand($operation, $params);
        
        $descriptorspec = [
            0 => ["pipe", "r"],  // stdin
            1 => ["pipe", "w"],  // stdout
            2 => ["pipe", "w"]   // stderr
        ];

        $process = proc_open($command, $descriptorspec, $pipes);
        
        if (!is_resource($process)) {
            throw new Exception("Не удалось запустить Go-бинарь");
        }

        // Отправляем данные если нужно
        if (!empty($params)) {
            fwrite($pipes[0], json_encode($params));
        }
        fclose($pipes[0]);

        // Читаем результат
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $return_value = proc_close($process);

        if ($return_value !== 0) {
            throw new Exception("Go-бинарь завершился с ошибкой: {$stderr}");
        }

        $result = json_decode($stdout, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Некорректный JSON ответ от Go-бинаря: " . json_last_error_msg());
        }

        return $result;
    }

    private function buildCommand(string $operation, array $params): string
    {
        $command = escapeshellarg($this->goBinaryPath) . " -operation=" . escapeshellarg($operation);
        
        foreach ($params as $key => $value) {
            $command .= " -" . escapeshellarg($key) . "=" . escapeshellarg((string)$value);
        }

        return $command;
    }

    private function parseBankResult(array $result): BankResult
    {
        $success = $result['success'] ?? false;
        $message = $result['message'] ?? null;
        $slipLines = $result['slip_lines'] ?? null;
        $resultCode = $result['result_code'] ?? ($success ? 0 : -1);

        return new BankResult($success, $message, $slipLines, $resultCode);
    }
}
