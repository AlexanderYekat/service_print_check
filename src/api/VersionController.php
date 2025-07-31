<?php

namespace App\Api;

require_once __DIR__ . '/BaseController.php';

use App\Api\BaseController;
// LoggerInterface в глобальном namespace

class VersionController extends BaseController
{
    public function __construct(\LoggerInterface $logger)
    {
        parent::__construct($logger);
    }

    protected function validateRequest(array $request): array
    {
        // Для получения версии валидация не требуется
        return $request;
    }

    protected function executeUseCase(array $request): array
    {
        return [
            'version' => VERSION_OF_PROGRAM ?: '2.0.0'
        ];
    }
}
