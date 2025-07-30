<?php
class SerialScaleAdapter implements ScaleInterface
{
    private string $comPort;

    public function __construct(string $comPort)
    {
        $this->comPort = $comPort;
    }

    public function getWeight(): WeightResult
    {
        $handle = @fopen($this->comPort, 'r+');
        if ($handle === false) {
            return new WeightResult(false, "Не удалось открыть порт {$this->comPort}");
        }

        stream_set_timeout($handle, 1, 0); // таймаут 1 сек

        $data = fread($handle, 100);
        fclose($handle);

        // Здесь нужен парсер под твой формат данных!
        $weight = $this->parseWeightData($data);

        if ($weight === null) {
            return new WeightResult(false, "Ошибка парсинга данных веса");
        }

        return new WeightResult(true, null, new Weight($weight, 'g'));
    }

    private function parseWeightData(string $data): ?float
    {
        // Реализуй парсинг по формату своего устройства!
        // Например, если данные — просто число:
        if (preg_match('/(\d+(\.\d+)?)/', $data, $matches)) {
            return floatval($matches[1]);
        }
        return null;
    }
}
