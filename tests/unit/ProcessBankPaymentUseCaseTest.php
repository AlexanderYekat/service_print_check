<?php

namespace Tests;

use App\Domain\Service\ProcessBankPaymentUseCase;
use App\Interface\BankTerminalInterface;
use App\Infrastructure\Logger\LoggerInterface;
use App\Domain\Model\OperationResult;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use InvalidArgumentException;

/**
 * Тесты для UseCase обработки банковских платежей
 */
class ProcessBankPaymentUseCaseTest extends TestCase
{
    /** @var BankTerminalInterface|MockObject */
    private $bankTerminalMock;
    
    /** @var LoggerInterface|MockObject */
    private $loggerMock;
    
    /** @var ProcessBankPaymentUseCase */
    private $useCase;

    protected function setUp(): void
    {
        $this->bankTerminalMock = $this->createMock(BankTerminalInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        
        $this->useCase = new ProcessBankPaymentUseCase(
            $this->bankTerminalMock,
            $this->loggerMock
        );
    }

    /**
     * Тест успешной оплаты
     */
    public function testPaymentSuccess(): void
    {
        // Arrange
        $amount = 100.50;
        $slipData = ['БАНКОВСКИЙ СЛИП', 'ОПЛАТА: 100.50 руб', 'ОДОБРЕНО'];
        
        $this->bankTerminalMock
            ->expects($this->once())
            ->method('pay')
            ->with($amount)
            ->willReturn(OperationResult::success($slipData));
        
        $this->loggerMock
            ->expects($this->atLeastOnce())
            ->method('info');
        
        // Act
        $result = $this->useCase->pay($amount);
        
        // Assert
        $this->assertTrue($result->isSuccess());
        $this->assertIsArray($result->getData());
        $this->assertArrayHasKey('transaction', $result->getData());
        $this->assertArrayHasKey('slip', $result->getData());
    }

    /**
     * Тест валидации отрицательной суммы
     */
    public function testPaymentWithNegativeAmount(): void
    {
        // Arrange
        $amount = -10.0;
        
        $this->loggerMock
            ->expects($this->atLeastOnce())
            ->method('error');
        
        // Act
        $result = $this->useCase->pay($amount);
        
        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertStringContains('положительной', $result->getErrorMessage());
    }

    /**
     * Тест валидации нулевой суммы
     */
    public function testPaymentWithZeroAmount(): void
    {
        // Arrange
        $amount = 0.0;
        
        $this->loggerMock
            ->expects($this->atLeastOnce())
            ->method('error');
        
        // Act
        $result = $this->useCase->pay($amount);
        
        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertStringContains('положительной', $result->getErrorMessage());
    }

    /**
     * Тест валидации слишком большой суммы
     */
    public function testPaymentWithTooLargeAmount(): void
    {
        // Arrange
        $amount = 1000000.0; // Больше лимита
        
        $this->loggerMock
            ->expects($this->atLeastOnce())
            ->method('error');
        
        // Act
        $result = $this->useCase->pay($amount);
        
        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertStringContains('слишком большая', $result->getErrorMessage());
    }

    /**
     * Тест успешного возврата
     */
    public function testRefundSuccess(): void
    {
        // Arrange
        $amount = 50.25;
        $slipData = ['БАНКОВСКИЙ СЛИП', 'ВОЗВРАТ: 50.25 руб', 'ОДОБРЕНО'];
        
        $this->bankTerminalMock
            ->expects($this->once())
            ->method('refund')
            ->with($amount)
            ->willReturn(OperationResult::success($slipData));
        
        $this->loggerMock
            ->expects($this->atLeastOnce())
            ->method('info');
        
        // Act
        $result = $this->useCase->refund($amount);
        
        // Assert
        $this->assertTrue($result->isSuccess());
        $this->assertIsArray($result->getData());
        $this->assertArrayHasKey('transaction', $result->getData());
        $this->assertArrayHasKey('slip', $result->getData());
    }

    /**
     * Тест успешной отмены операции
     */
    public function testCancelSuccess(): void
    {
        // Arrange
        $amount = 75.00;
        $slipData = ['БАНКОВСКИЙ СЛИП', 'ОТМЕНА: 75.00 руб', 'ОДОБРЕНО'];
        
        $this->bankTerminalMock
            ->expects($this->once())
            ->method('cancel')
            ->with($amount)
            ->willReturn(OperationResult::success($slipData));
        
        $this->loggerMock
            ->expects($this->atLeastOnce())
            ->method('info');
        
        // Act
        $result = $this->useCase->cancel($amount);
        
        // Assert
        $this->assertTrue($result->isSuccess());
        $this->assertIsArray($result->getData());
        $this->assertArrayHasKey('transaction', $result->getData());
        $this->assertArrayHasKey('slip', $result->getData());
    }

    /**
     * Тест успешного закрытия смены
     */
    public function testCloseShiftSuccess(): void
    {
        // Arrange
        $slipData = ['БАНКОВСКИЙ СЛИП', 'ЗАКРЫТИЕ СМЕНЫ', 'УСПЕШНО'];
        
        $this->bankTerminalMock
            ->expects($this->once())
            ->method('closeShift')
            ->willReturn(OperationResult::success($slipData));
        
        $this->loggerMock
            ->expects($this->atLeastOnce())
            ->method('info');
        
        // Act
        $result = $this->useCase->closeShift();
        
        // Assert
        $this->assertTrue($result->isSuccess());
        $this->assertIsArray($result->getData());
        $this->assertArrayHasKey('operation', $result->getData());
        $this->assertArrayHasKey('slip', $result->getData());
        $this->assertArrayHasKey('timestamp', $result->getData());
        $this->assertEquals('shift_close', $result->getData()['operation']);
    }

    /**
     * Тест обработки ошибки от банковского терминала
     */
    public function testPaymentTerminalError(): void
    {
        // Arrange
        $amount = 100.0;
        $errorMessage = 'Нет связи с банком';
        
        $this->bankTerminalMock
            ->expects($this->once())
            ->method('pay')
            ->with($amount)
            ->willReturn(OperationResult::failure($errorMessage));
        
        $this->loggerMock
            ->expects($this->atLeastOnce())
            ->method('error');
        
        // Act
        $result = $this->useCase->pay($amount);
        
        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertStringContains($errorMessage, $result->getErrorMessage());
    }

    /**
     * Тест обработки исключения в банковском терминале
     */
    public function testPaymentTerminalException(): void
    {
        // Arrange
        $amount = 100.0;
        
        $this->bankTerminalMock
            ->expects($this->once())
            ->method('pay')
            ->with($amount)
            ->willThrowException(new \Exception('Unexpected error'));
        
        $this->loggerMock
            ->expects($this->atLeastOnce())
            ->method('error');
        
        // Act
        $result = $this->useCase->pay($amount);
        
        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertStringContains('Внутренняя ошибка системы', $result->getErrorMessage());
    }

    /**
     * Тест валидации суммы с большим количеством знаков после запятой
     */
    public function testPaymentWithTooManyDecimals(): void
    {
        // Arrange
        $amount = 100.123; // 3 знака после запятой
        
        $this->loggerMock
            ->expects($this->atLeastOnce())
            ->method('error');
        
        // Act
        $result = $this->useCase->pay($amount);
        
        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertStringContains('не более 2 знаков', $result->getErrorMessage());
    }
}
