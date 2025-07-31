<?php

require_once __DIR__ . '/BaseController.php';

class VersionController extends BaseController
{
    public function __construct(LoggerInterface $logger)
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
