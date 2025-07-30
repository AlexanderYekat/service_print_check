<?php
class BankPaymentController
{
    private ProcessBankPaymentUseCase $useCase;

    public function __construct(ProcessBankPaymentUseCase $useCase)
    {
        $this->useCase = $useCase;
    }

    public function handle(array $request): void
    {
        try {
            $operation = $request['operation'] ?? null;
            $params = $request['params'] ?? [];

            switch ($operation) {
                case 'PayMoney':
                    $amount = (float)($params['amount'] ?? 0);
                    $result = $this->useCase->pay($amount);
                    break;
                case 'ReturnMoney':
                    $amount = (float)($params['amount'] ?? 0);
                    $result = $this->useCase->refund($amount);
                    break;
                case 'CloseShiftTerminal':
                    $result = $this->useCase->closeShift();
                    break;
                default:
                    ErrorResponseHelper::json([
                        'success' => false,
                        'message' => 'Неизвестная банковская операция'
                    ], 400);
                    return;
            }

            ErrorResponseHelper::json([
                'success' => $result->success,
                'message' => $result->message,
                'data'    => [
                    'success'  => $result->success,
                    'response' => $result->slipLines ?? [],
                    'resultCode' => $result->resultCode ?? null
                ]
            ]);
        } catch (\Throwable $e) {
            ErrorResponseHelper::json([
                'success' => false,
                'message' => 'Ошибка сервиса: ' . $e->getMessage()
            ], 500);
        }
    }
}
