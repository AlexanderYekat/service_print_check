class GetWeightUseCaseTest extends \PHPUnit\Framework\TestCase {
    public function testReturnsFakeWeight() {
        $adapter = new FakeScaleAdapter();
        $useCase = new GetWeightUseCase($adapter);
        $result = $useCase->execute();

        $this->assertTrue($result->success);
        $this->assertEquals("1234.56", $result->weight);
    }
}
