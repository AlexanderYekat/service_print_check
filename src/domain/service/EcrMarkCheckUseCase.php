<?php

namespace App\Domain\Service;

use App\Domain\Model\OperationResult;
use App\Domain\Model\MarkingCode;
use App\Interface\EcrMarkCheckGateway;

require_once __DIR__ . '/../model/OperationResult.php';
require_once __DIR__ . '/../model/MarkingCode.php';
// Используем автозагрузку вместо require_once
require_once __DIR__ . '/MarkCheckRegistry.php';

/**
 * Use Case для асинхронной проверки марки на ККТ
 * Работает через очередь с возможностью получения результата по taskId
 * Сохраняет результаты в реестр для последующего сопоставления
 */
class EcrMarkCheckUseCase 
{
    private EcrMarkCheckGateway $gateway;
    private MarkCheckRegistry $registry;
    
    /** @var array<string, array> Карта taskId -> [code, context] для сохранения в реестр */
    private array $taskMapping = [];
    
    public function __construct(EcrMarkCheckGateway $gateway, MarkCheckRegistry $registry) 
    {
        $this->gateway = $gateway;
        $this->registry = $registry;
    }
    
    /**
     * Ставит задачу проверки марки на ККТ в очередь
     * @param MarkingCode $code Код маркировки для проверки
     * @param array $context Дополнительный контекст (inn, gtin и пр.)
     * @return string taskId — идентификатор задания для последующего запроса статуса
     */
    public function enqueue(MarkingCode $code, array $context = []): string 
    {
        $taskId = $this->gateway->enqueueMarkCheck($code, $context);
        
        // Сохраняем связь taskId с кодом и контекстом для будущего сохранения результата
        $this->taskMapping[$taskId] = [
            'code' => $code,
            'context' => $context
        ];
        
        return $taskId;
    }
    
    /**
     * Получает результат проверки марки по taskId
     * Автоматически сохраняет результат в реестр при успешном получении
     * 
     * @param string $taskId Идентификатор задания
     * @return OperationResult Результат с user_status и machine_data
     */
    public function getResult(string $taskId): OperationResult 
    {
        $result = $this->gateway->getMarkCheckResult($taskId);
        
        // Если есть mapping для этой задачи и результат получен, сохраняем в реестр
        if (isset($this->taskMapping[$taskId]) && $result->success) {
            $taskData = $this->taskMapping[$taskId];
            $this->registry->storeEcrCheckResult(
                $taskData['code'], 
                $result, 
                $taskData['context']
            );
            
            // Удаляем из mapping чтобы не накапливались данные
            unset($this->taskMapping[$taskId]);
        }
        
        return $result;
    }
    
    /**
     * Сохраняет результат проверки в реестр для уже известной марки
     * Используется когда результат получен через другой механизм
     */
    public function storeResult(MarkingCode $code, OperationResult $result, array $context = []): void
    {
        $this->registry->storeEcrCheckResult($code, $result, $context);
    }
    
    /**
     * Проверяет, была ли марка уже проверена на ККТ ранее
     */
    public function hasBeenChecked(MarkingCode $code): bool
    {
        return $this->registry->hasEcrCheck($code);
    }
    
    /**
     * Получает результат предыдущей проверки на ККТ, если есть
     */
    public function getPreviousResult(MarkingCode $code): ?array
    {
        return $this->registry->getEcrCheckResult($code);
    }
}