<?php
class CloseShiftController
{
    private CloseShiftUseCase $useCase;

    public function __construct(CloseShiftUseCase $useCase)
    {
        $this->useCase = $useCase;
    }

    public function handle(array $request): void
    {
        try {
            $cashier = $request['cashier'] ?? '';
            $result = $this->useCase->execute($cashier);
            ErrorResponseHelper::json([
                'success' => $result->success,
                'message' => $result->message,
                'data'    => [
                    'success' => $result->success,
                    'response' => $result->printedLines ?? [],
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
