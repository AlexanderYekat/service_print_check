<?php
class FakeScaleAdapter implements ScaleInterface
{
    public function getWeight(): WeightResult
    {
        return new WeightResult(true, null, 123.5);
    }
}
