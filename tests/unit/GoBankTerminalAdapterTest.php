<?php

namespace Tests\Unit;

use App\Infrastructure\Bank\GoBankTerminalAdapter;
use App\Interface\SettingsStorageInterface;
use App\Infrastructure\Logger\LoggerInterface;
use App\Domain\Model\OperationResult;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Тесты для адаптера банковского терминала
 */
class GoBankTerminalAdapterTest extends TestCase
{
    /** @var SettingsStorageInterface|MockObject */
    private $settingsStorageMock;
    
    /** @var LoggerInterface|MockObject */
    private $loggerMock;
    
    /** @var GoBankTerminalAdapter */
    private $adapter;

    protected function setUp(): void
    {
        $this->settingsStorageMock = $this->createMock(SettingsStorageInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        
        // Настраиваем моки
        $this->settingsStorageMock
            ->method('get')
            ->with('bank', [])
            ->willReturn([
                'binary_path' => './bank/mainbeznal.exe',
                'timeout' => 30,
                'emulation' => true // Включаем эмуляцию для тестов
            ]);
        
        $this->adapter = new GoBankTerminalAdapter(
            $this->settingsStorageMock,
            $this->loggerMock
        );
    }

    /**
     * Тест успешной оплаты в режиме эмуляции
     */
    public function testPaymentSuccess(): void
    {
        // Arrange
        $amount = 100.50;
        
        // Ожидаем логирование
        $this->loggerMock
            ->expects($this->atLeastOnce())
            ->method('info');
        
        // Act
        $result = $this->adapter->pay($amount);
        
        // Assert
        $this->assertInstanceOf(OperationResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertIsArray($result->getData());
        $this->assertNotEmpty($result->getData());
        
        // Проверяем содержимое слипа
        $slip = $result->getData();
        $this->assertContains('ЭМУЛЯЦИЯ БАНКА', implode(' ', $slip));
        $this->assertContains('PAY', strtoupper(implode(' ', $slip)));
    }

    /**
     * Тест успешного возврата в режиме эмуляции
     */
    public function testRefundSuccess(): void
    {
        // Arrange
        $amount = 50.25;
        
        // Ожидаем логирование
        $this->loggerMock
            ->expects($this->atLeastOnce())
            ->method('info');
        
        // Act
        $result = $this->adapter->refund($amount);
        
        // Assert
        $this->assertInstanceOf(OperationResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertIsArray($result->getData());
        $this->assertNotEmpty($result->getData());
        
        // Проверяем содержимое слипа
        $slip = $result->getData();
        $this->assertContains('ЭМУЛЯЦИЯ БАНКА', implode(' ', $slip));
        $this->assertContains('RETURN', strtoupper(implode(' ', $slip)));
    }

    /**
     * Тест успешной отмены операции в режиме эмуляции
     */
    public function testCancelSuccess(): void
    {
        // Arrange
        $amount = 75.00;
        
        // Ожидаем логирование
        $this->loggerMock
            ->expects($this->atLeastOnce())
            ->method('info');
        
        // Act
        $result = $this->adapter->cancel($amount);
        
        // Assert
        $this->assertInstanceOf(OperationResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertIsArray($result->getData());
        $this->assertNotEmpty($result->getData());
        
        // Проверяем содержимое слипа
        $slip = $result->getData();
        $this->assertContains('ЭМУЛЯЦИЯ БАНКА', implode(' ', $slip));
        $this->assertContains('CANCEL', strtoupper(implode(' ', $slip)));
    }

    /**
     * Тест успешного закрытия смены в режиме эмуляции
     */
    public function testCloseShiftSuccess(): void
    {
        // Ожидаем логирование
        $this->loggerMock
            ->expects($this->atLeastOnce())
            ->method('info');
        
        // Act
        $result = $this->adapter->closeShift();
        
        // Assert
        $this->assertInstanceOf(OperationResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertIsArray($result->getData());
        $this->assertNotEmpty($result->getData());
        
        // Проверяем содержимое слипа
        $slip = $result->getData();
        $this->assertContains('ЭМУЛЯЦИЯ БАНКА', implode(' ', $slip));
        $this->assertContains('CLOSE_SHIFT', strtoupper(implode(' ', $slip)));
    }

    /**
     * Тест ошибки при отсутствии бинаря
     */
    public function testBinaryNotFoundError(): void
    {
        // Arrange - создаем адаптер с реальным режимом и несуществующим бинарём
        $this->settingsStorageMock = $this->createMock(SettingsStorageInterface::class);
        $this->settingsStorageMock
            ->method('get')
            ->with('bank', [])
            ->willReturn([
                'binary_path' => '/nonexistent/path/binary.exe',
                'timeout' => 30,
                'emulation' => false // Отключаем эмуляцию
            ]);
        
        $adapter = new GoBankTerminalAdapter(
            $this->settingsStorageMock,
            $this->loggerMock
        );
        
        // Ожидаем логирование ошибки
        $this->loggerMock
            ->expects($this->atLeastOnce())
            ->method('error');
        
        // Act
        $result = $adapter->pay(100.0);
        
        // Assert
        $this->assertInstanceOf(OperationResult::class, $result);
        $this->assertFalse($result->isSuccess());
        $this->assertStringContains('Go-бинарь не найден', $result->getErrorMessage());
    }

    /**
     * Тест конфигурации по умолчанию
     */
    public function testDefaultConfiguration(): void
    {
        // Arrange - создаем адаптер с пустой конфигурацией
        $this->settingsStorageMock = $this->createMock(SettingsStorageInterface::class);
        $this->settingsStorageMock
            ->method('get')
            ->with('bank', [])
            ->willReturn([]); // Пустая конфигурация
        
        // Ожидаем логирование с дефолтными значениями
        $this->loggerMock
            ->expects($this->once())
            ->method('info')
            ->with($this->stringContains('binary_path=./bank/mainbeznal.exe'));
        
        // Act
        new GoBankTerminalAdapter(
            $this->settingsStorageMock,
            $this->loggerMock
        );
        
        // Assert - проверка проходит через логирование в конструкторе
        $this->assertTrue(true);
    }

    /**
     * Тест валидации нулевой суммы через операцию оплаты
     */
    public function testZeroAmountHandling(): void
    {
        // Arrange
        $amount = 0.0;
        
        // Act & Assert
        // В режиме эмуляции даже нулевая сумма должна обработаться, 
        // так как валидация происходит в UseCase
        $result = $this->adapter->pay($amount);
        $this->assertInstanceOf(OperationResult::class, $result);
    }
}