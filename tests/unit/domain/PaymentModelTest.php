<?php

require_once __DIR__ . '/../../BaseTestCase.php';
require_once __DIR__ . '/../../../src/domain/model/Payment.php';

/**
 * Unit тест для модели Payment
 */
class PaymentModelTest extends BaseTestCase
{
    protected function getTests(): array
    {
        return [
            'testValidCashPayment',
            'testValidCardPayment', 
            'testInvalidPaymentType',
            'testNegativeAmount',
            'testZeroAmount',
            'testEmptyPaymentType',
            'testPaymentToArray'
        ];
    }
    
    public function testValidCashPayment(): bool
    {
        $payment = new Payment('cash', 100.50);
        
        $this->assertEquals('cash', $payment->getType());
        $this->assertEquals(100.50, $payment->getAmount());
        $this->assertTrue($payment->isCash());
        
        return true;
    }
    
    public function testValidCardPayment(): bool
    {
        $payment = new Payment('card', 250.75);
        
        $this->assertEquals('card', $payment->getType());
        $this->assertEquals(250.75, $payment->getAmount());
        $this->assertFalse($payment->isCash());
        
        return true;
    }
    
    public function testInvalidPaymentType(): bool
    {
        $this->assertThrows(
            fn() => new Payment('bitcoin', 100.00),
            'InvalidArgumentException'
        );
        
        return true;
    }
    
    public function testNegativeAmount(): bool
    {
        $this->assertThrows(
            fn() => new Payment('cash', -50.00),
            'InvalidArgumentException'
        );
        
        return true;
    }
    
    public function testZeroAmount(): bool
    {
        $this->assertThrows(
            fn() => new Payment('cash', 0.00),
            'InvalidArgumentException'
        );
        
        return true;
    }
    
    public function testEmptyPaymentType(): bool
    {
        $this->assertThrows(
            fn() => new Payment('', 100.00),
            'InvalidArgumentException'
        );
        
        return true;
    }
    
    public function testPaymentToArray(): bool
    {
        $payment = new Payment('cash', 123.45);
        $array = $payment->toArray();
        
        $expected = ['type' => 'cash', 'amount' => 123.45];
        $this->assertEquals($expected, $array);
        
        return true;
    }
}

// Запуск если файл вызван напрямую
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new PaymentModelTest();
    $result = $test->run();
    exit($result ? 0 : 1);
}