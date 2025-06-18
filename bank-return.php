<?php
// ... существующий код ...

    } elseif ($uri === '/api/return-money' && $method === 'POST') {
        $logger->info("Получен запрос на возврат денег.");
        header('Content-Type: application/json; charset=utf-8');

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $amount = $data['amount'] ?? 0.0;

        if (!is_numeric($amount) || $amount <= 0) {
            $logger->error("Некорректная сумма для возврата: " . $amount);
            echo json_encode(['success' => false, 'message' => 'Некорректная сумма для возврата.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $scriptPath = __DIR__ . DIRECTORY_SEPARATOR . 'bank-return.ps1';
        $outputFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bank_return_output_' . uniqid() . '.json';
        $taskName = 'BankReturnTask_' . uniqid(); // Уникальное имя для задания

        // Команда для запуска PowerShell скрипта через Планировщик заданий
        // /SC ONCE: Запустить один раз
        // /ST 00:00: Запустить сейчас
        // /TR: Путь к программе/скрипту, который нужно запустить
        // /RU SYSTEM: Запускать от имени SYSTEM (или любого другого пользователя с правами)
        // /IT: Запускать только когда пользователь вошел в систему (Interactive Task)
        // /Z: Удалить задание после выполнения
        $schtasksCommand = "schtasks /create /tn \"{$taskName}\" /tr \"powershell.exe -NoProfile -ExecutionPolicy Bypass -File \\\"{$scriptPath}\\\" -amount " . escapeshellarg($amount) . " > \\\"{$outputFile}\\\" 2>&1\" /sc ONCE /st 00:00 /ru SYSTEM /IT /f /Z";

        // Запускаем команду создания задания в фоновом режиме
        // 2>&1 > NUL: Перенаправляем вывод и ошибки в NUL, чтобы не засорять логи PHP
        $commandResult = shell_exec("cmd /c \"{$schtasksCommand}\" 2>&1 > NUL");
        
        if (strpos($commandResult, 'ERROR') !== false || strpos($commandResult, 'Failed') !== false) {
             $errorMessage = "Не удалось создать/запустить запланированную задачу: " . $commandResult;
             $logger->error($errorMessage);
             echo json_encode(['status' => 'error', 'message' => $errorMessage], JSON_UNESCAPED_UNICODE);
             exit;
        }

        $logger->info("Задача возврата денег запущена через Планировщик заданий: {$taskName}. Результат ожидается в файле: " . $outputFile);
        echo json_encode(['status' => 'success', 'message' => 'Операция возврата запущена. Результат будет записан в файл.', 'outputFile' => $outputFile, 'taskName' => $taskName], JSON_UNESCAPED_UNICODE);

    // ... остальной код ...