<?php

namespace App\Infrastructure\Scale;

use App\Interface\ScaleInterface;
use App\Interface\HealthCheckable;
use App\Domain\Model\OperationResult;
use App\Infrastructure\Scale\TScale8Driver;

class SerialScaleAdapter implements ScaleInterface, HealthCheckable
{
    private $settingsPath;

    public function __construct($settingsPath)
    {
        $this->settingsPath = $settingsPath;
    }


    public function getWeight(): OperationResult
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
            return OperationResult::failure("Ошибка подключения к весам: {$connectErrorDesc}");
        }
        list($success, $readErrorDesc, $weight) = $scaleDriver->ReadWeight();
        if (!$success) {
            $scaleDriver->Close();
            return OperationResult::failure("Ошибка чтения веса: {$readErrorDesc}");
        }
        $scaleDriver->Close();
        return OperationResult::success(['weight' => $weight], 'Вес получен успешно');
    }

    /**
     * {@inheritdoc}
     */
    public function checkHealth(): array
    {
        $startTime = microtime(true);
        
        try {
            $settings = json_decode(file_get_contents($this->settingsPath), true);
            $scaleSettings = $settings['scale'] ?? [];
            $emulation = $scaleSettings['emulation'] ?? false;

            if ($emulation) {
                return [
                    'status' => 'ok',
                    'message' => 'Весы работают в режиме эмуляции',
                    'details' => ['emulation' => true],
                    'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
                ];
            }

            // Пытаемся подключиться к весам
            $scaleDriver = new TScale8Driver(
                $scaleSettings['com_port'] ?? 1001,
                $scaleSettings['baud_rate'] ?? 18,
                $scaleSettings['model'] ?? 38,
                $scaleSettings['com_class'] ?? 'AddIn.Scale8',
                $emulation
            );
            
            list($isOpened, $connectErrorDesc) = $scaleDriver->Open();
            if (!$isOpened) {
                return [
                    'status' => 'warning',
                    'message' => 'Не удается подключиться к весам: ' . $connectErrorDesc,
                    'details' => ['connection_error' => $connectErrorDesc],
                    'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
                ];
            }

            // Проверяем чтение веса
            list($success, $readErrorDesc, $weight) = $scaleDriver->ReadWeight();
            $scaleDriver->Close();

            if (!$success) {
                return [
                    'status' => 'warning',
                    'message' => 'Ошибка чтения веса с весов: ' . $readErrorDesc,
                    'details' => ['read_error' => $readErrorDesc],
                    'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
                ];
            }

            return [
                'status' => 'ok',
                'message' => 'Весы доступны и готовы к работе',
                'details' => ['test_weight' => $weight],
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Ошибка при проверке весов: ' . $e->getMessage(),
                'details' => ['exception' => get_class($e)],
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getComponentName(): string
    {
        return 'scales';
    }
}
