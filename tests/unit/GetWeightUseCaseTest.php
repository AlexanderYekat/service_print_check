<?php

require_once __DIR__ . '/../src/domain/service/GetWeightUseCase.php';
require_once __DIR__ . '/../src/infrastructure/scale/FakeScaleAdapter.php';
require_once __DIR__ . '/../src/domain/model/OperationResult.php';
require_once __DIR__ . '/../src/interface/ScaleInterface.php';

class GetWeightUseCaseTest extends \PHPUnit\Framework\TestCase {
    public function testReturnsFakeWeight() {
        $adapter = new FakeScaleAdapter();
        $useCase = new GetWeightUseCase($adapter);
        $result = $useCase->execute();

        $this->assertTrue($result->success);
        $this->assertEquals(123.5, $result->getData('weight'));
    }
}
