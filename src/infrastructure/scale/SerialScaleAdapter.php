<?php

require_once __DIR__ . '/../../interface/ScaleInterface.php';
require_once __DIR__ . '/../../domain/model/WeightResult.php';

class SerialScaleAdapter implements ScaleInterface
{
    private $settingsPath;

    public function __construct($settingsPath)
    {
        $this->settingsPath = $settingsPath;
    }


    public function getWeight(): WeightResult
    {
        $settings = json_decode(file_get_contents($this->settingsPath), true);
        $scaleSettings = $settings['scale'] ?? [];
        $comPort = $scaleSettings['com_port'];
        $baudRate = $scaleSettings['baud_rate'];
        $model = $scaleSettings['model'];
        $comClass = $scaleSettings['com_class'];
        $emulation = $scaleSettings['emulation'];

        $scaleDriver = new TScale8Driver($comPort, $baudRate, $model, $comClass, $emulation);
        list($isOpened, $connectErrorDesc) = $scaleDriver->Open();
        if (!$isOpened) {
            return new WeightResult(false, "Ошибка подключения к весам: {$connectErrorDesc}");
        }
        list($success, $readErrorDesc, $weight) = $scaleDriver->ReadWeight();
        if (!$success) {
            $scaleDriver->Close();
            return new WeightResult(false, "Ошибка чтения веса: {$readErrorDesc}");
        }
        $scaleDriver->Close();
        return new WeightResult(true, null, $weight);
    }
}
