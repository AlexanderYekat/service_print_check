<?php

namespace App\Api;

require_once __DIR__ . '/BaseController.php';

use App\Api\BaseController;
use App\Api\BusinessLogicException;
use App\Domain\Service\GetWeightUseCase;
use App\Infrastructure\Logger\LoggerInterface;

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
        
        if (!$result->success) {
            throw new BusinessLogicException($result->error ?? $result->message ?? 'Ошибка получения веса');
        }

        return [
            'weight' => $result->data['weight'] ?? null,
            'message' => $result->message
        ];
    }
}
