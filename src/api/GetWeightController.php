<?php

require_once __DIR__ . '/BaseController.php';

class GetWeightController extends BaseController
{
    private GetWeightUseCase $useCase;

    public function __construct(GetWeightUseCase $useCase, LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->useCase = $useCase;
    }

    protected function validateRequest(array $request): array
    {
        // Для получения веса валидация не требуется
        return $request;
    }

    protected function executeUseCase(array $request): array
    {
        $result = $this->useCase->execute();
        
        if (!$result->success && $result->message) {
            throw new InfrastructureException($result->message);
        }

        return [
            'weight' => $result->weight ?? null,
            'message' => $result->message
        ];
    }
}
