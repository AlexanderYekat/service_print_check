<?php

require_once __DIR__ . '/../model/OperationResult.php';
require_once __DIR__ . '/../model/MarkingCode.php';
require_once __DIR__ . '/../../interface/PermitMarkCheckGateway.php';

/**
 * Use Case для синхронной проверки марки в разрешительном режиме
 * Работает через внешний API синхронно
 */
class PermitMarkCheckUseCase 
{
    private PermitMarkCheckGateway $gateway;
    
    public function __construct(PermitMarkCheckGateway $gateway) 
    {
        $this->gateway = $gateway;
    }
    
    /**
     * Выполняет синхронную проверку марки в разрешительном режиме
     * @param MarkingCode $code Код маркировки для проверки
     * @param array $context Дополнительный контекст (может содержать fiscalDriveNumber)
     * @return OperationResult Результат с user_status и machine_data
     */
    public function execute(MarkingCode $code, array $context = []): OperationResult 
    {
        return $this->gateway->checkPermit($code, $context);
    }
}