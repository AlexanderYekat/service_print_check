<?php

/**
 * Скрипт для запуска воркера обработки асинхронных задач проверки марки на ККТ
 * Использование: php scripts/run_ecr_worker.php
 */

require_once __DIR__ . '/../src/infrastructure/queue/EcrMarkCheckWorker.php';
require_once __DIR__ . '/../src/infrastructure/queue/EcrMarkCheckQueue.php';

echo "=== Запуск воркера проверки марки на ККТ ===\n";

// Настройки из конфигурации
$config = [
    'api_url' => getenv('HONEST_SIGN_API_URL') ?: 'https://api.markirovka.ru',
    'api_key' => getenv('HONEST_SIGN_API_KEY') ?: '',
    'queue_path' => 'logs/ecr_mark_queue.json',
    'results_path' => 'logs/ecr_mark_results.json',
    'timeout' => 30,
    'worker_interval' => 5 // секунд между проверками очереди
];

echo "API URL: {$config['api_url']}\n";
echo "Очередь: {$config['queue_path']}\n";
echo "Результаты: {$config['results_path']}\n";
echo "Интервал проверки: {$config['worker_interval']} сек\n\n";

// Создаем компоненты
$queue = new EcrMarkCheckQueue($config['queue_path'], $config['results_path']);
$worker = new EcrMarkCheckWorker(
    $queue,
    $config['api_url'],
    $config['api_key'],
    $config['timeout']
);

// Основной цикл воркера
$running = true;
$iteration = 0;

// Обработка сигналов для корректного завершения
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function() use (&$running) {
        echo "\nПолучен сигнал завершения. Останавливаем воркер...\n";
        $running = false;
    });
    
    pcntl_signal(SIGINT, function() use (&$running) {
        echo "\nПолучен Ctrl+C. Останавливаем воркер...\n";
        $running = false;
    });
}

echo "Воркер запущен. Для остановки нажмите Ctrl+C\n\n";

while ($running) {
    $iteration++;
    echo "[" . date('Y-m-d H:i:s') . "] Итерация #{$iteration}\n";
    
    try {
        $results = $worker->processQueue();
        
        if (empty($results)) {
            echo "  Очередь пуста\n";
        } else {
            echo "  Обработано задач: " . count($results) . "\n";
            foreach ($results as $result) {
                $status = $result['status'] === 'success' ? '✅' : '❌';
                echo "  {$status} {$result['task_id']}: {$result['status']}\n";
                if (isset($result['error'])) {
                    echo "     Ошибка: {$result['error']}\n";
                }
            }
        }
        
        // Обрабатываем сигналы
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
        
        if ($running) {
            echo "  Ожидание {$config['worker_interval']} сек...\n\n";
            sleep($config['worker_interval']);
        }
        
    } catch (Exception $e) {
        echo "❌ Ошибка в воркере: {$e->getMessage()}\n";
        echo "  Ожидание {$config['worker_interval']} сек перед повтором...\n\n";
        sleep($config['worker_interval']);
    }
}

echo "Воркер остановлен.\n";