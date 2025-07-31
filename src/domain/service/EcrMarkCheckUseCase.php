<?php

require_once __DIR__ . '/../model/OperationResult.php';
require_once __DIR__ . '/../model/MarkingCode.php';
require_once __DIR__ . '/../../interface/EcrMarkCheckGateway.php';

/**
 * Use Case для асинхронной проверки марки на ККТ
 * Работает через очередь с возможностью получения результата по taskId
 */
class EcrMarkCheckUseCase 
{
    private EcrMarkCheckGateway $gateway;
    
    public function __construct(EcrMarkCheckGateway $gateway) 
    {
        $this->gateway = $gateway;
    }
    
    /**
     * Ставит задачу проверки марки на ККТ в очередь
     * @param MarkingCode $code Код маркировки для проверки
     * @param array $context Дополнительный контекст (inn, gtin и пр.)
     * @return string taskId — идентификатор задания для последующего запроса статуса
     */
    public function enqueue(MarkingCode $code, array $context = []): string 
    {
        return $this->gateway->enqueueMarkCheck($code, $context);
    }
    
    /**
     * Получает результат проверки марки по taskId
     * @param string $taskId Идентификатор задания
     * @return OperationResult Результат с user_status и machine_data
     */
    public function getResult(string $taskId): OperationResult 
    {
        return $this->gateway->getMarkCheckResult($taskId);
    }
}