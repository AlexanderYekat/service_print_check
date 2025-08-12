<?php

namespace Tests\Integration;

require_once __DIR__ . '/../BaseTestCase.php';

use App\Infrastructure\Bank\GoBankTerminalAdapter;
use App\Infrastructure\Logger\FileLogger;
use App\Infrastructure\SettingsStorage\JsonFileSettingsStorage;
use App\Domain\Model\OperationResult;

class BankTerminalGoIntegrationTest extends BaseTestCase
{
    private GoBankTerminalAdapter $adapter;
    
    public function __construct()
    {
        $this->setupTestEnvironment();
    }
    
    protected function getTests(): array
    {
        return [
            'testTempDirectoryCreation',
            'testOperationParametersWriting',
            'testGoBinaryExecution',
            'testResultWaiting',
            'testTempFilesCleanup',
            'testResultParsing',
            'testSlipProcessing',
            'testEmulationMode',
            'testErrorHandling'
        ];
    }
    
    private function setupTestEnvironment(): void
    {
        $settingsStorage = new JsonFileSettingsStorage(__DIR__ . '/../../config/settings.json');
        $logger = new FileLogger(__DIR__ . '/../../logs/bank_go_test.log');
        $this->adapter = new GoBankTerminalAdapter($settingsStorage, $logger);
    }
    
    public function testTempDirectoryCreation(): bool
    {
        $reflection = new \ReflectionClass($this->adapter);
        $method = $reflection->getMethod('createTempDirectory');
        $method->setAccessible(true);
        
        $tempDir = $method->invoke($this->adapter);
        
        $this->assertTrue(is_dir($tempDir));
        $this->assertTrue(is_writable($tempDir));
        
        rmdir($tempDir);
        
        return true;
    }
    
    public function testOperationParametersWriting(): bool
    {
        $tempDir = sys_get_temp_dir() . '/bank_test_' . uniqid();
        mkdir($tempDir);
        
        $reflection = new \ReflectionClass($this->adapter);
        $method = $reflection->getMethod('writeOperationParameters');
        $method->setAccessible(true);
        
        $method->invoke($this->adapter, $tempDir, 'pay', 100.50);
        
        $paramsFile = $tempDir . '/params.json';
        $this->assertTrue(file_exists($paramsFile));
        
        $params = json_decode(file_get_contents($paramsFile), true);
        $this->assertEquals('pay', $params['operation']);
        $this->assertEquals(100.50, $params['amount']);
        
        unlink($paramsFile);
        rmdir($tempDir);
        
        return true;
    }
    
    public function testGoBinaryExecution(): bool
    {
        $bankConfig = $this->adapter->getSettingsStorage()->get('bank', []);
        $binaryPath = $bankConfig['binary_path'] ?? './bank/mainbeznal.exe';
        
        if (!file_exists($binaryPath)) {
            echo "⚠️  Go-бинарнь не найден по пути: {$binaryPath}\n";
            return true;
        }
        
        $tempDir = sys_get_temp_dir() . '/bank_test_' . uniqid();
        mkdir($tempDir);
        
        $params = ['operation' => 'pay', 'amount' => 50.00];
        file_put_contents($tempDir . '/params.json', json_encode($params));
        
        $reflection = new \ReflectionClass($this->adapter);
        $method = $reflection->getMethod('executeBinary');
        $method->setAccessible(true);
        
        $method->invoke($this->adapter, $tempDir);
        
        $pidFile = $tempDir . '/pid.txt';
        if (file_exists($pidFile)) {
            $pid = (int)file_get_contents($pidFile);
            $this->assertGreaterThan(0, $pid);
        }
        
        if (file_exists($pidFile)) unlink($pidFile);
        if (file_exists($tempDir . '/params.json')) unlink($tempDir . '/params.json');
        rmdir($tempDir);
        
        return true;
    }
    
    public function testResultWaiting(): bool
    {
        $tempDir = sys_get_temp_dir() . '/bank_test_' . uniqid();
        mkdir($tempDir);
        
        $testResult = [
            'success' => true,
            'message' => 'Тестовая операция',
            'slipLines' => ['БАНКОВСКИЙ СЛИП', 'ТЕСТОВАЯ ОПЕРАЦИЯ']
        ];
        file_put_contents($tempDir . '/result.json', json_encode($testResult));
        
        $reflection = new \ReflectionClass($this->adapter);
        $method = $reflection->getMethod('waitForResult');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->adapter, $tempDir);
        
        $this->assertTrue(is_array($result));
        $this->assertTrue($result['success']);
        $this->assertEquals('Тестовая операция', $result['message']);
        
        unlink($tempDir . '/result.json');
        rmdir($tempDir);
        
        return true;
    }
    
    public function testTempFilesCleanup(): bool
    {
        $tempDir = sys_get_temp_dir() . '/bank_test_' . uniqid();
        mkdir($tempDir);
        
        file_put_contents($tempDir . '/test1.txt', 'test1');
        file_put_contents($tempDir . '/test2.txt', 'test2');
        mkdir($tempDir . '/subdir');
        file_put_contents($tempDir . '/subdir/test3.txt', 'test3');
        
        $reflection = new \ReflectionClass($this->adapter);
        $method = $reflection->getMethod('cleanupTempFiles');
        $method->setAccessible(true);
        
        $method->invoke($this->adapter, $tempDir);
        
        $this->assertFalse(is_dir($tempDir));
        
        return true;
    }
    
    public function testResultParsing(): bool
    {
        $testResult = [
            'success' => true,
            'message' => 'Оплата успешна',
            'slipLines' => [
                'БАНКОВСКИЙ СЛИП',
                'ОПЛАТА: 100.50 руб',
                'ОДОБРЕНО',
                'ТРАНЗАКЦИЯ: 123456789'
            ]
        ];
        
        $reflection = new \ReflectionClass($this->adapter);
        $method = $reflection->getMethod('parseResult');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->adapter, $testResult);
        
        $this->assertTrue($result instanceof OperationResult);
        $this->assertTrue($result->isSuccess());
        
        $data = $result->getData();
        $this->assertArrayHasKey('slip', $data);
        $this->assertEquals(4, count($data['slip']));
        
        return true;
    }
    
    public function testSlipProcessing(): bool
    {
        $testSlip = "БАНКОВСКИЙ СЛИП\nОПЛАТА: 150.75 руб\nОДОБРЕНО\nТРАНЗАКЦИЯ: 987654321\nДАТА: 01.01.2024";
        
        $reflection = new \ReflectionClass($this->adapter);
        $method = $reflection->getMethod('processSlip');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->adapter, $testSlip);
        
        $this->assertTrue(is_array($result));
        $this->assertEquals(5, count($result));
        $this->assertEquals('БАНКОВСКИЙ СЛИП', $result[0]);
        $this->assertStringContains('150.75', $result[1]);
        
        return true;
    }
    
    public function testEmulationMode(): bool
    {
        $reflection = new \ReflectionClass($this->adapter);
        $emulationProperty = $reflection->getProperty('emulation');
        $emulationProperty->setAccessible(true);
        $emulationProperty->setValue($this->adapter, true);
        
        $result = $this->adapter->pay(200.00);
        
        $this->assertTrue($result->isSuccess());
        
        $data = $result->getData();
        $this->assertArrayHasKey('slip', $data);
        
        $slipText = implode(' ', $data['slip']);
        $this->assertTrue(
            str_contains($slipText, 'ЭМУЛЯЦИЯ') || str_contains($slipText, '200')
        );
        
        $emulationProperty->setValue($this->adapter, false);
        
        return true;
    }
    
    public function testErrorHandling(): bool
    {
        $testResult = [
            'success' => false,
            'error_code' => 1001,
            'message' => 'Ошибка подключения к терминалу'
        ];
        
        $reflection = new \ReflectionClass($this->adapter);
        $method = $reflection->getMethod('parseResult');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->adapter, $testResult);
        
        $this->assertFalse($result->isSuccess());
        
        $errorMessage = $result->getErrorMessage();
        $this->assertStringContains('Ошибка подключения к терминалу', $errorMessage);
        
        return true;
    }
} 