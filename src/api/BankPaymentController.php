<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/request/RequestValidator.php';

class BankPaymentController extends BaseController
{
    private ProcessBankPaymentUseCase $useCase;

    public function __construct(ProcessBankPaymentUseCase $useCase, LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->useCase = $useCase;
    }

    protected function validateRequest(array $request): array
    {
        RequestValidator::validateRequired($request, ['operation']);
        
        $operation = RequestValidator::validateEnum(
            $request, 
            'operation', 
            ['pay', 'refund', 'close_shift', 'PayMoney', 'ReturnMoney', 'CloseShiftTerminal']
        );

        // Нормализуем операции (поддержка старого API)
        $normalizedOperation = $this->normalizeOperation($operation);
        $request['operation'] = $normalizedOperation;

        // Валидация суммы для операций с деньгами
        if (in_array($normalizedOperation, ['pay', 'refund'])) {
            // Поддержка как прямого указания amount, так и params.amount
            $amount = $request['amount'] ?? $request['params']['amount'] ?? null;
            if ($amount === null) {
                throw new ValidationException("Для операции {$normalizedOperation} требуется указать amount");
            }
            $request['amount'] = RequestValidator::validateNumeric(['amount' => $amount], 'amount', 0.01);
        }

        return $request;
    }

    protected function executeUseCase(array $request): array
    {
        $transaction = BankTransaction::fromArray($request);
        $result = $this->useCase->execute($transaction);
        
        if (!$result->success && $result->message) {
            throw new BusinessLogicException($result->message);
        }

        return [
            'success' => $result->success,
            'message' => $result->message,
            'result_code' => $result->resultCode,
            'slip_lines' => $result->slipLines ?? []
        ];
    }

    private function normalizeOperation(string $operation): string
    {
        $mapping = [
            'PayMoney' => 'pay',
            'ReturnMoney' => 'refund',
            'CloseShiftTerminal' => 'close_shift'
        ];

        return $mapping[$operation] ?? $operation;
    }
}
