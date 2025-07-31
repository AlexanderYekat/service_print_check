<?php

namespace App\Domain\Service;

use App\Interface\PermitMarkCheckGateway;
use App\Domain\Model\OperationResult;
use App\Domain\Model\MarkingCode;

/**
 * Use Case для синхронной проверки марки в разрешительном режиме
 * Работает через внешний API синхронно и сохраняет результаты в реестр
 */
class PermitMarkCheckUseCase 
{
    private PermitMarkCheckGateway $gateway;
    private MarkCheckRegistry $registry;
    
    public function __construct(PermitMarkCheckGateway $gateway, MarkCheckRegistry $registry) 
    {
        $this->gateway = $gateway;
        $this->registry = $registry;
    }
    
    /**
     * Выполняет синхронную проверку марки в разрешительном режиме
     * Сохраняет результат в реестр для последующего сопоставления
     * 
     * @param MarkingCode $code Код маркировки для проверки
     * @param array $context Дополнительный контекст (может содержать fiscalDriveNumber)
     * @return OperationResult Результат с user_status и machine_data
     */
    public function execute(MarkingCode $code, array $context = []): OperationResult 
    {
        // Выполняем проверку через gateway
        $result = $this->gateway->checkPermit($code, $context);
        
        // Сохраняем результат в реестр для последующего сопоставления
        $this->registry->storePermitCheckResult($code, $result, $context);
        
        return $result;
    }
    
    /**
     * Проверяет, была ли марка уже проверена ранее
     */
    public function hasBeenChecked(MarkingCode $code): bool
    {
        return $this->registry->hasPermitCheck($code);
    }
    
    /**
     * Получает результат предыдущей проверки, если есть
     */
    public function getPreviousResult(MarkingCode $code): ?array
    {
        return $this->registry->getPermitCheckResult($code);
    }
}