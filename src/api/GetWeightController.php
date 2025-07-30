<?php
class GetWeightController
{
    private GetWeightUseCase $useCase;

    public function __construct(GetWeightUseCase $useCase)
    {
        $this->useCase = $useCase;
    }

    public function handle(): void
    {
        try {
            $result = $this->useCase->execute();
            ErrorResponseHelper::json([
                'success' => $result->success,
                'message' => $result->message,
                'data'    => [
                    'weight' => $result->weight ?? null,
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
