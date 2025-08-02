<?php

namespace App\Api;

use App\Api\BaseController;
use App\Api\Request\RequestValidator;
use App\Api\Response\ResponseFormatter;
use App\Domain\Service\ProcessBankPaymentUseCase;
use App\Infrastructure\Logger\LoggerInterface;
use Exception;

/**
 * Контроллер для обработки банковских платежей
 * 
 * Обрабатывает HTTP-запросы для операций с банковским терминалом:
 * - оплата (pay)
 * - возврат (refund) 
 * - отмена (cancel)
 * - закрытие смены (close_shift)
 */
class BankPaymentController extends BaseController
{
    private ProcessBankPaymentUseCase $bankPaymentUseCase;

    public function __construct(
        ProcessBankPaymentUseCase $bankPaymentUseCase,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
        $this->bankPaymentUseCase = $bankPaymentUseCase;
    }

    /**
     * Обработать банковский платеж
     *
     * @param array $request HTTP-запрос
     * @return array Результат операции
     */
    public function handle(array $request = []): void
    {
        try {
            // Валидация запроса
            $validatedRequest = $this->validateRequest($request);
            
            // Выполнение операции
            $result = $this->executeOperation($validatedRequest);
            
            // Отправка успешного ответа через BaseController
            echo json_encode($this->formatResponse($result), JSON_UNESCAPED_UNICODE);
            
        } catch (\Exception $e) {
            $this->logger->error("Ошибка обработки банковского платежа: " . $e->getMessage());
            echo json_encode(ResponseFormatter::error($e->getMessage()), JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Реализация абстрактного метода из BaseController
     * 
     * @param array $request Валидированный запрос
     * @return array Результат выполнения
     */
    protected function executeUseCase(array $request): array
    {
        $result = $this->executeOperation($request);
        return $this->formatResponse($result);
    }

    /**
     * Валидация входящего запроса
     *
     * @param array $request Данные запроса
     * @return array Валидированные данные
     * @throws Exception При ошибке валидации
     */
    protected function validateRequest(array $request): array
    {
        // Проверяем обязательные поля
        RequestValidator::validateRequired($request, ['operation']);
        
        // Валидируем операцию
        $operation = RequestValidator::validateEnum(
            $request, 
            'operation', 
            ['pay', 'refund', 'cancel', 'close_shift', 'PayMoney', 'ReturnMoney', 'CancelMoney', 'CloseShiftTerminal']
        );

        // Нормализуем операции (поддержка старого API)
        $normalizedOperation = $this->normalizeOperation($operation);
        $request['operation'] = $normalizedOperation;

        // Валидация суммы для операций с деньгами
        if (in_array($normalizedOperation, ['pay', 'refund', 'cancel'])) {
            $amount = $this->extractAmount($request);
            $request['amount'] = RequestValidator::validateNumeric(['amount' => $amount], 'amount', 0.01);
        }

        return $request;
    }

    /**
     * Извлечь сумму из запроса (поддержка разных форматов)
     *
     * @param array $request Данные запроса
     * @return float|null Сумма операции
     * @throws Exception Если сумма не указана
     */
    private function extractAmount(array $request): ?float
    {
        // Поддержка разных форматов указания суммы
        $amount = $request['amount'] ?? 
                  $request['params']['amount'] ?? 
                  $request['sum'] ?? 
                  null;
        
        if ($amount === null) {
            throw new Exception("Для операции {$request['operation']} требуется указать сумму");
        }
        
        return (float)$amount;
    }

    /**
     * Выполнить банковскую операцию
     *
     * @param array $request Валидированный запрос
     * @return array Результат операции
     * @throws Exception При ошибке выполнения
     */
    private function executeOperation(array $request): array
    {
        $operation = $request['operation'];
        
        switch ($operation) {
            case 'pay':
                $operationResult = $this->bankPaymentUseCase->pay($request['amount']);
                break;
                
            case 'refund':
                $operationResult = $this->bankPaymentUseCase->refund($request['amount']);
                break;
                
            case 'cancel':
                $operationResult = $this->bankPaymentUseCase->cancel($request['amount']);
                break;
                
            case 'close_shift':
                $operationResult = $this->bankPaymentUseCase->closeShift();
                break;
                
            default:
                throw new Exception("Неподдерживаемая операция: {$operation}");
        }

        if (!$operationResult->isSuccess()) {
            throw new Exception($operationResult->getErrorMessage());
        }

        return $operationResult->getData();
    }

    /**
     * Форматировать ответ для клиента
     *
     * @param array $data Данные операции
     * @return array Отформатированный ответ
     */
    private function formatResponse(array $data): array
    {
        $response = [
            'success' => true,
            'message' => 'Операция выполнена успешно'
        ];

        // Добавляем слип если есть
        if (isset($data['slip'])) {
            $response['slip_lines'] = $data['slip'];
        }

        // Добавляем информацию о транзакции если есть
        if (isset($data['transaction'])) {
            $transaction = $data['transaction'];
            $response['transaction'] = [
                'type' => $transaction->type ?? 'unknown',
                'amount' => $transaction->amount ?? 0
            ];
        }

        // Добавляем дополнительные данные операции
        if (isset($data['operation'])) {
            $response['operation'] = $data['operation'];
        }

        if (isset($data['timestamp'])) {
            $response['timestamp'] = $data['timestamp'];
        }

        return ResponseFormatter::success($response);
    }

    /**
     * Нормализация названий операций (поддержка legacy API)
     *
     * @param string $operation Оригинальное название операции
     * @return string Нормализованное название
     */
    private function normalizeOperation(string $operation): string
    {
        $mapping = [
            // Legacy поддержка
            'PayMoney' => 'pay',
            'ReturnMoney' => 'refund', 
            'CancelMoney' => 'cancel',
            'CloseShiftTerminal' => 'close_shift',
            
            // Альтернативные названия
            'payment' => 'pay',
            'return' => 'refund',
            'cancellation' => 'cancel',
            'shift_close' => 'close_shift'
        ];

        return $mapping[$operation] ?? strtolower($operation);
    }

    /**
     * Получить описание операции для логирования
     *
     * @param string $operation Название операции
     * @return string Описание операции
     */
    private function getOperationDescription(string $operation): string
    {
        $descriptions = [
            'pay' => 'Оплата через банковский терминал',
            'refund' => 'Возврат денежных средств',
            'cancel' => 'Отмена банковской операции', 
            'close_shift' => 'Закрытие смены банковского терминала'
        ];

        return $descriptions[$operation] ?? "Неизвестная операция: {$operation}";
    }
}
