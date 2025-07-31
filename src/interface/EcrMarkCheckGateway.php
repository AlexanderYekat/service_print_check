<?php

namespace App\Interface;

use App\Domain\Model\MarkingCode;
use App\Domain\Model\OperationResult;

/**
 * Интерфейс для асинхронной проверки марки на ККТ
 * Работает через очередь и позволяет получать результат по taskId
 */
interface EcrMarkCheckGateway
{
    /**
     * Ставит задачу проверки марки на ККТ в очередь (асинхронно).
     * @param MarkingCode $code Код маркировки для проверки
     * @param array $context Дополнительный контекст (inn, gtin и пр.)
     * @return string taskId — идентификатор задания (для последующего запроса статуса)
     */
    public function enqueueMarkCheck(MarkingCode $code, array $context = []): string;

    /**
     * Получает результат проверки по taskId 
     * (может быть статус "выполняется", "завершено", "ошибка" и пр.)
     * @param string $taskId Идентификатор задания
     * @return OperationResult c user_status + machine_data (itemInfoCheckResult и пр.).
     */
    public function getMarkCheckResult(string $taskId): OperationResult;
}