<?php
class GoBankTerminalAdapter implements BankTerminalInterface {
    public function pay(float $amount): BankResult {
        // 1. Запустить Go-бинарь с нужными параметрами (shell_exec, proc_open, etc)
        // 2. Прочитать результат (через файл, stdout)
        // 3. Распарсить ответ, вернуть BankResult
    }
    public function refund(float $amount): BankResult { ... }
    public function closeShift(): BankResult { ... }
}
