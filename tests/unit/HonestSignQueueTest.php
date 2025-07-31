<?php

require_once __DIR__ . '/../../src/infrastructure/queue/HonestSignQueue.php';
require_once __DIR__ . '/../../src/domain/model/MarkingCode.php';

class HonestSignQueueTest
{
    private string $testQueuePath = 'tests/temp/test_queue.json';

    public function setUp()
    {
        // Создание папки для тестов
        $dir = dirname($this->testQueuePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Очистка тестового файла
        if (file_exists($this->testQueuePath)) {
            unlink($this->testQueuePath);
        }
    }

    public function tearDown()
    {
        // Очистка после тестов
        if (file_exists($this->testQueuePath)) {
            unlink($this->testQueuePath);
        }
    }

    public function testAddToQueue()
    {
        $queue = new HonestSignQueue($this->testQueuePath);
        $code = new MarkingCode("01234567890123456789");
        
        $queue->addToQueue($code, 'validate');
        
        $status = $queue->getQueueStatus();
        assert($status['total'] === 1, "Queue should have 1 item");
        assert($status['pending'] === 1, "Queue should have 1 pending item");
        
        echo "✅ testAddToQueue passed\n";
    }

    public function testQueueStatus()
    {
        $queue = new HonestSignQueue($this->testQueuePath);
        
        $status = $queue->getQueueStatus();
        assert($status['total'] === 0, "Empty queue should have 0 items");
        
        // Добавляем несколько элементов
        $code1 = new MarkingCode("code1");
        $code2 = new MarkingCode("code2");
        
        $queue->addToQueue($code1);
        $queue->addToQueue($code2);
        
        $status = $queue->getQueueStatus();
        assert($status['total'] === 2, "Queue should have 2 items");
        assert($status['pending'] === 2, "Queue should have 2 pending items");
        
        echo "✅ testQueueStatus passed\n";
    }

    public function runAllTests()
    {
        echo "Running HonestSignQueue tests...\n";
        $this->setUp();
        
        $this->testAddToQueue();
        $this->tearDown();
        $this->setUp();
        
        $this->testQueueStatus();
        $this->tearDown();
        
        echo "All HonestSignQueue tests passed! ✅\n\n";
    }
}