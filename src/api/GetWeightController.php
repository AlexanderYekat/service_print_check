<?php

namespace App\Api;

require_once __DIR__ . '/BaseController.php';

use App\Api\BaseController;
use App\Api\InfrastructureException;
use App\Domain\Service\GetWeightUseCase;
// LoggerInterface в глобальном namespace

class GetWeightController extends BaseController
{
    private GetWeightUseCase $useCase;

    public function __construct(GetWeightUseCase $useCase, \LoggerInterface $logger)
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
