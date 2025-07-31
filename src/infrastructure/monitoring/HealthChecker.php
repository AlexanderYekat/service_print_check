<?php

require_once __DIR__ . '/../../interface/HealthCheckable.php';

/**
 * Проверка состояния всех инфраструктурных компонентов системы
 * 
 * Использует HealthCheckable интерфейс для унифицированной проверки
 * состояния банка, ККТ, весов, Честного Знака и других сервисов
 */
class HealthChecker
{
    private array $components = [];

    /**
     * Добавить компонент для мониторинга
     * 
     * @param HealthCheckable $component Компонент, реализующий HealthCheckable
     */
    public function addComponent(HealthCheckable $component): void
    {
        $this->components[$component->getComponentName()] = $component;
    }

    /**
     * Проверить состояние всех зарегистрированных компонентов
     * 
     * @return array Общий отчёт о состоянии системы
     */
    public function checkAll(): array
    {
        $results = [];
        
        foreach ($this->components as $name => $component) {
            $results[$name] = $this->checkComponent($component);
        }

        return [
            'overall_status' => $this->getOverallStatus($results),
            'timestamp' => date('Y-m-d H:i:s'),
            'components_count' => count($this->components),
            'components' => $results
        ];
    }

    /**
     * Проверить состояние одного компонента
     * 
     * @param HealthCheckable $component Компонент для проверки
     * @return array Результат проверки
     */
    private function checkComponent(HealthCheckable $component): array
    {
        try {
            $result = $component->checkHealth();
            
            return [
                'status' => $result['status'],
                'message' => $result['message'],
                'response_time_ms' => $result['response_time_ms'] ?? null,
                'last_check' => date('Y-m-d H:i:s'),
                'details' => $result['details'] ?? null
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Исключение при проверке: ' . $e->getMessage(),
                'response_time_ms' => null,
                'last_check' => date('Y-m-d H:i:s'),
                'details' => ['exception' => get_class($e)]
            ];
        }
    }

    /**
     * Определить общий статус системы на основе статусов компонентов
     * 
     * @param array $results Результаты проверки всех компонентов
     * @return string Общий статус: 'ok', 'degraded', 'critical'
     */
    private function getOverallStatus(array $results): string
    {
        $hasError = false;
        $hasWarning = false;

        foreach ($results as $result) {
            if ($result['status'] === 'error') {
                $hasError = true;
            } elseif ($result['status'] === 'warning') {
                $hasWarning = true;
            }
        }

        if ($hasError) {
            return 'critical';
        } elseif ($hasWarning) {
            return 'degraded';
        }

        return 'ok';
    }

    /**
     * Сохранить отчёт о состоянии в файл
     * 
     * @param string $filePath Путь к файлу для сохранения
     */
    public function saveHealthReport(string $filePath): void
    {
        $report = $this->checkAll();
        file_put_contents($filePath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}