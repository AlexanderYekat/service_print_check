<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Domain\Service\GetWeightUseCase;
use App\Infrastructure\Scale\FakeScaleAdapter;
use App\Domain\Model\OperationResult;
use App\Interface\ScaleInterface;

class GetWeightUseCaseTest extends \PHPUnit\Framework\TestCase {
    public function testReturnsFakeWeight() {
        $adapter = new FakeScaleAdapter();
        $useCase = new GetWeightUseCase($adapter);
        $result = $useCase->execute();

        $this->assertTrue($result->success);
        $this->assertEquals(123.5, $result->getData('weight'));
    }
}
