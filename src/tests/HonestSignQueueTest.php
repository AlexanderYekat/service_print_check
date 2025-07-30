<?php

require_once __DIR__ . '/../infrastructure/queue/HonestSignQueue.php';
require_once __DIR__ . '/../domain/model/MarkingCode.php';

class HonestSignQueueTest
{
    private string $testQueuePath = '/tmp/test_queue.json';

    public function setUp()
    {
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
        $code = new MarkingCode("01234567890123456789", "1234567890", "4607184110117");
        
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

    public function testClearCompleted()
    {
        $queue = new HonestSignQueue($this->testQueuePath);
        $code = new MarkingCode("test_code");
        
        $queue->addToQueue($code);
        
        // Изначально нет завершенных
        $cleared = $queue->clearCompleted();
        assert($cleared === 0, "Should clear 0 completed items");
        
        echo "✅ testClearCompleted passed\n";
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
        $this->setUp();
        
        $this->testClearCompleted();
        $this->tearDown();
        
        echo "All HonestSignQueue tests passed! ✅\n\n";
    }
}