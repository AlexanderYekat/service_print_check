<?php

require_once __DIR__ . '/../model/MarkingCode.php';
require_once __DIR__ . '/../model/OperationResult.php';

/**
 * Реестр результатов проверок маркировочных кодов
 * Обеспечивает сопоставление марок по cleanCode независимо от исходного формата
 */
class MarkCheckRegistry
{
    /**
     * Хранилище результатов проверок
     * Ключ: cleanCode марки
     * Значение: массив с данными проверки
     * @var array<string, array>
     */
    private array $permitCheckResults = [];
    
    /**
     * Хранилище результатов проверок ККТ
     * Ключ: cleanCode марки  
     * Значение: массив с данными проверки
     * @var array<string, array>
     */
    private array $ecrCheckResults = [];

    /**
     * Сохраняет результат проверки в разрешительном режиме
     */
    public function storePermitCheckResult(MarkingCode $markingCode, OperationResult $result, array $context = []): void
    {
        $cleanCode = $markingCode->getCleanCode();
        
        $this->permitCheckResults[$cleanCode] = [
            'marking_code' => $markingCode,
            'result' => $result,
            'context' => $context,
            'checked_at' => date('Y-m-d H:i:s'),
            'check_type' => 'permit'
        ];
    }

    /**
     * Сохраняет результат проверки на ККТ
     */
    public function storeEcrCheckResult(MarkingCode $markingCode, OperationResult $result, array $context = []): void
    {
        $cleanCode = $markingCode->getCleanCode();
        
        $this->ecrCheckResults[$cleanCode] = [
            'marking_code' => $markingCode,
            'result' => $result,
            'context' => $context,
            'checked_at' => date('Y-m-d H:i:s'),
            'check_type' => 'ecr'
        ];
    }

    /**
     * Получает результат проверки в разрешительном режиме по маркировке
     */
    public function getPermitCheckResult(MarkingCode $markingCode): ?array
    {
        $cleanCode = $markingCode->getCleanCode();
        return $this->permitCheckResults[$cleanCode] ?? null;
    }

    /**
     * Получает результат проверки на ККТ по маркировке
     */
    public function getEcrCheckResult(MarkingCode $markingCode): ?array
    {
        $cleanCode = $markingCode->getCleanCode();
        return $this->ecrCheckResults[$cleanCode] ?? null;
    }

    /**
     * Проверяет, была ли марка проверена в разрешительном режиме
     */
    public function hasPermitCheck(MarkingCode $markingCode): bool
    {
        $cleanCode = $markingCode->getCleanCode();
        return isset($this->permitCheckResults[$cleanCode]);
    }

    /**
     * Проверяет, была ли марка проверена на ККТ
     */
    public function hasEcrCheck(MarkingCode $markingCode): bool
    {
        $cleanCode = $markingCode->getCleanCode();
        return isset($this->ecrCheckResults[$cleanCode]);
    }

    /**
     * Получает все результаты проверок для марки (permit + ecr)
     */
    public function getAllCheckResults(MarkingCode $markingCode): array
    {
        return [
            'permit' => $this->getPermitCheckResult($markingCode),
            'ecr' => $this->getEcrCheckResult($markingCode)
        ];
    }

    /**
     * Проверяет, прошла ли марка все необходимые проверки
     */
    public function isFullyChecked(MarkingCode $markingCode): bool
    {
        return $this->hasPermitCheck($markingCode) && $this->hasEcrCheck($markingCode);
    }

    /**
     * Получает статистику по проверкам
     */
    public function getCheckStats(): array
    {
        $permitSuccess = 0;
        $permitFailed = 0;
        
        foreach ($this->permitCheckResults as $check) {
            if ($check['result']->success) {
                $permitSuccess++;
            } else {
                $permitFailed++;
            }
        }
        
        $ecrSuccess = 0;
        $ecrFailed = 0;
        
        foreach ($this->ecrCheckResults as $check) {
            if ($check['result']->success) {
                $ecrSuccess++;
            } else {
                $ecrFailed++;
            }
        }
        
        return [
            'permit_total' => count($this->permitCheckResults),
            'permit_success' => $permitSuccess,
            'permit_failed' => $permitFailed,
            'ecr_total' => count($this->ecrCheckResults),
            'ecr_success' => $ecrSuccess,
            'ecr_failed' => $ecrFailed
        ];
    }

    /**
     * Очищает все результаты проверок
     */
    public function clear(): void
    {
        $this->permitCheckResults = [];
        $this->ecrCheckResults = [];
    }

    /**
     * Очищает результаты проверок старше указанного количества секунд
     */
    public function clearOldResults(int $maxAgeSeconds = 3600): void
    {
        $cutoffTime = time() - $maxAgeSeconds;
        
        $this->permitCheckResults = array_filter(
            $this->permitCheckResults,
            function($check) use ($cutoffTime) {
                return strtotime($check['checked_at']) > $cutoffTime;
            }
        );
        
        $this->ecrCheckResults = array_filter(
            $this->ecrCheckResults,
            function($check) use ($cutoffTime) {
                return strtotime($check['checked_at']) > $cutoffTime;
            }
        );
    }

    /**
     * Возвращает все cleanCode, которые были проверены
     */
    public function getAllCheckedCodes(): array
    {
        return array_unique(array_merge(
            array_keys($this->permitCheckResults),
            array_keys($this->ecrCheckResults)
        ));
    }
}