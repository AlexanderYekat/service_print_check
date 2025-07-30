<?php

use PHPUnit\Framework\TestCase;

class PrintCheckUseCaseTest extends TestCase
{
    public function testFakePrintCheck()
    {
        $adapter = new FakePrinterAdapter();
        $useCase = new PrintCheckUseCase($adapter);

        $check = new Check(
            [['name' => 'Товар', 'quantity' => 1, 'price' => 100]],
            'Тестовый кассир',
            [['type' => 'cash', 'amount' => 100]],
            'sell',
            'osn'
        );

        $result = $useCase->execute($check);

        $this->assertTrue($result->success);
        $this->assertNotEmpty($result->printedLines);
    }
}
