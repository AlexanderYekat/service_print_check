<?php
class PrintCheckController
{
    private PrintCheckUseCase $useCase;

    public function __construct(PrintCheckUseCase $useCase)
    {
        $this->useCase = $useCase;
    }

    public function handle(array $request): void
    {
        try {
            $check = Check::fromArray($request);
            $result = $this->useCase->execute($check);
            ErrorResponseHelper::json([
                'success' => $result->success,
                'message' => $result->message,
                'data'    => [
                    'success'  => $result->success,
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
