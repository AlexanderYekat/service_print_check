<?php

namespace Tests\Integration;

require_once __DIR__ . '/../BaseTestCase.php';

use App\Api\BankPaymentController;
use App\Domain\Service\ProcessBankPaymentUseCase;
use App\Infrastructure\Bank\GoBankTerminalAdapter;
use App\Infrastructure\Logger\FileLogger;
use App\Infrastructure\SettingsStorage\JsonFileSettingsStorage;

class BankTerminalIntegrationTest extends BaseTestCase
{
    private BankPaymentController $controller;
    private ProcessBankPaymentUseCase $useCase;
    private GoBankTerminalAdapter $adapter;
    
    public function __construct()
    {
        $this->setupTestEnvironment();
    }
    
    protected function getTests(): array
    {
        return [
            'testSuccessfulPaymentFlow',
            'testSuccessfulRefundFlow',
            'testSuccessfulCancelFlow', 
            'testSuccessfulCloseShiftFlow',
            'testNegativeAmountValidation',
            'testZeroAmountValidation',
            'testInvalidOperationValidation',
            'testLegacyApiSupport',
            'testBankTerminalHealthCheck'
        ];
    }
    
    private function setupTestEnvironment(): void
    {
        $settingsStorage = new JsonFileSettingsStorage(__DIR__ . '/../../config/settings.json');
        $logger = new FileLogger(__DIR__ . '/../../logs/bank_test.log');
        $this->adapter = new GoBankTerminalAdapter($settingsStorage, $logger);
        $this->useCase = new ProcessBankPaymentUseCase($this->adapter, $logger);
        $this->controller = new BankPaymentController($this->useCase, $logger);
    }
    
    public function testSuccessfulPaymentFlow(): bool
    {
        $request = ['operation' => 'pay', 'amount' => 150.75];
        
        ob_start();
        $this->controller->handle($request);
        $response = ob_get_clean();
        
        $responseData = json_decode($response, true);
        
        $this->assertTrue($responseData['success'] ?? false);
        $this->assertArrayHasKey('transaction', $responseData['data']);
        
        $transaction = $responseData['data']['transaction'];
        $this->assertEquals(150.75, $transaction['amount']);
        $this->assertEquals('payment', $transaction['operation_type']);
        
        return true;
    }
    
    public function testSuccessfulRefundFlow(): bool
    {
        $request = ['operation' => 'refund', 'amount' => 50.25];
        
        ob_start();
        $this->controller->handle($request);
        $response = ob_get_clean();
        
        $responseData = json_decode($response, true);
        
        $this->assertTrue($responseData['success'] ?? false);
        
        $transaction = $responseData['data']['transaction'];
        $this->assertEquals('refund', $transaction['operation_type']);
        
        return true;
    }
    
    public function testSuccessfulCancelFlow(): bool
    {
        $request = ['operation' => 'cancel', 'amount' => 100.00];
        
        ob_start();
        $this->controller->handle($request);
        $response = ob_get_clean();
        
        $responseData = json_decode($response, true);
        
        $this->assertTrue($responseData['success'] ?? false);
        
        $transaction = $responseData['data']['transaction'];
        $this->assertEquals('cancel', $transaction['operation_type']);
        
        return true;
    }
    
    public function testSuccessfulCloseShiftFlow(): bool
    {
        $request = ['operation' => 'close_shift'];
        
        ob_start();
        $this->controller->handle($request);
        $response = ob_get_clean();
        
        $responseData = json_decode($response, true);
        
        $this->assertTrue($responseData['success'] ?? false);
        $this->assertArrayHasKey('shift_close', $responseData['data']);
        
        return true;
    }
    
    public function testNegativeAmountValidation(): bool
    {
        $request = ['operation' => 'pay', 'amount' => -50.00];
        
        ob_start();
        $this->controller->handle($request);
        $response = ob_get_clean();
        
        $responseData = json_decode($response, true);
        
        $this->assertFalse($responseData['success'] ?? true);
        $this->assertStringContains('положительной', $responseData['error']);
        
        return true;
    }
    
    public function testZeroAmountValidation(): bool
    {
        $request = ['operation' => 'pay', 'amount' => 0.00];
        
        ob_start();
        $this->controller->handle($request);
        $response = ob_get_clean();
        
        $responseData = json_decode($response, true);
        
        $this->assertFalse($responseData['success'] ?? true);
        
        return true;
    }
    
    public function testInvalidOperationValidation(): bool
    {
        $request = ['operation' => 'invalid_operation', 'amount' => 100.00];
        
        ob_start();
        $this->controller->handle($request);
        $response = ob_get_clean();
        
        $responseData = json_decode($response, true);
        
        $this->assertFalse($responseData['success'] ?? true);
        
        return true;
    }
    
    public function testLegacyApiSupport(): bool
    {
        $request = ['operation' => 'PayMoney', 'amount' => 75.50];
        
        ob_start();
        $this->controller->handle($request);
        $response = ob_get_clean();
        
        $responseData = json_decode($response, true);
        
        $this->assertTrue($responseData['success'] ?? false);
        
        $transaction = $responseData['data']['transaction'];
        $this->assertEquals('payment', $transaction['operation_type']);
        
        return true;
    }
    
    public function testBankTerminalHealthCheck(): bool
    {
        $healthResult = $this->adapter->checkHealth();
        
        $this->assertArrayHasKey('status', $healthResult);
        $this->assertArrayHasKey('component', $healthResult);
        $this->assertEquals('BankTerminal', $healthResult['component']);
        
        return true;
    }
} 