<?php

namespace App\Domain\Service;

use App\Interface\PrinterInterface;
use App\Interface\SettingsStorageInterface;
use App\Domain\Model\Check;
use App\Domain\Model\OperationResult;
use App\Domain\Service\MarkMatchingService;

/**
 * Use Case для печати чека с сопоставлением результатов проверок маркировок
 */
class PrintCheckUseCase {
    private PrinterInterface $printer;
    private SettingsStorageInterface $settings;
    private MarkMatchingService $markMatchingService;
    
    public function __construct(
        PrinterInterface $printer, 
        SettingsStorageInterface $settings,
        MarkMatchingService $markMatchingService
    ) {
        $this->printer = $printer;
        $this->settings = $settings;
        $this->markMatchingService = $markMatchingService;
    }
    
    /**
     * Печатает чек с автоматическим сопоставлением результатов проверок маркировок
     * 
     * @param Check $check Чек для печати
     * @param bool $requireFullChecks Требовать ли наличие проверок permit+ecr для всех марок
     * @return OperationResult
     * @throws MarkMatchingException Если есть непроверенные маркировки при requireFullChecks=true
     */
    public function execute(Check $check, bool $requireFullChecks = false): OperationResult {
        // 1. Получаем настройки
        $cfg = $this->settings->load();
        
        // 2. Сопоставляем маркировки в чеке с результатами проверок
        try {
            $markCheckData = $this->markMatchingService->matchCheckItems($check, $requireFullChecks);
        } catch (MarkMatchingException $e) {
            return new OperationResult(
                false,
                [],
                'Ошибка сопоставления маркировок: ' . $e->getMessage()
            );
        }
        
        // 3. Печатаем чек с данными проверок маркировок
        return $this->printer->printCheck($check, $markCheckData);
    }
    
    /**
     * Получает статистику сопоставления маркировок для чека
     */
    public function getMatchingStats(Check $check): array
    {
        return $this->markMatchingService->getMatchingStats($check);
    }
    
    /**
     * Проверяет, готов ли чек к печати (все маркировки проверены)
     */
    public function isReadyToPrint(Check $check): bool
    {
        return $this->markMatchingService->areAllMarksChecked($check);
    }
    
    /**
     * Печатает чек без проверки сопоставления маркировок (legacy mode)
     */
    public function executeLegacy(Check $check): OperationResult {
        $cfg = $this->settings->load();
        return $this->printer->printCheck($check, []);
    }
}