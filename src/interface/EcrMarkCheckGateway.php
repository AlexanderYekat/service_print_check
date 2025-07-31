<?php

require_once __DIR__ . '/../domain/model/MarkingCode.php';
require_once __DIR__ . '/../domain/model/OperationResult.php';

/**
 * Интерфейс для асинхронной проверки марки на ККТ
 * Работает через очередь и позволяет получать результат по taskId
 */
interface EcrMarkCheckGateway
{
    /**
     * Ставит задачу проверки марки на ККТ в очередь (асинхронно).
     * @param MarkingCode $code Код маркировки для проверки
     * @return string taskId — идентификатор задания (для последующего запроса статуса)
     */
    public function enqueueMarkCheck(MarkingCode $code): string;

    /**
     * Получает результат проверки по taskId 
     * (может быть статус "выполняется", "завершено", "ошибка" и пр.)
     * @param string $taskId Идентификатор задания
     * @return OperationResult c user_status + machine_data (itemInfoCheckResult и пр.).
     */
    public function getMarkCheckResult(string $taskId): OperationResult;
}