<?php
class FakeScaleAdapter implements ScaleInterface
{
    public function getWeight(): WeightResult
    {
        return new WeightResult(true, null, new Weight(42.0, 'g'));
    }
}
