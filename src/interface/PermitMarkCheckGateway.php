<?php

require_once __DIR__ . '/../domain/model/MarkingCode.php';
require_once __DIR__ . '/../domain/model/OperationResult.php';

/**
 * Интерфейс для синхронной проверки марки в разрешительном режиме
 * Взаимодействует с внешним API (Честный Знак) синхронно
 */
interface PermitMarkCheckGateway
{
    /**
     * Синхронно вызывает внешний API (например, Честный Знак).
     * @param MarkingCode $code Код маркировки для проверки
     * @return OperationResult с user_status + machine_data (uuid, time и пр.).
     */
    public function checkPermit(MarkingCode $code): OperationResult;
}