<?php
// domain/service/GetWeightUseCase.php
class GetWeightUseCase {
    private ScaleInterface $scale;
    public function __construct(ScaleInterface $scale) {
        $this->scale = $scale;
    }
    public function execute(): WeightResult {
        return $this->scale->getWeight();
    }
}
