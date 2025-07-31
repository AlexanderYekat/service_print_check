<?php

namespace App\Interface;

use App\Domain\Model\MarkingCode;
use App\Domain\Model\OperationResult;

/**
 * Интерфейс для синхронной проверки марки в разрешительном режиме
 * Взаимодействует с внешним API (Честный Знак) синхронно
 */
interface PermitMarkCheckGateway
{
    /**
     * Синхронно вызывает внешний API (например, Честный Знак).
     * @param MarkingCode $code Код маркировки для проверки
     * @param array $context Дополнительный контекст (может содержать fiscalDriveNumber)
     * @return OperationResult с user_status + machine_data (uuid, time и пр.).
     */
    public function checkPermit(MarkingCode $code, array $context = []): OperationResult;
}