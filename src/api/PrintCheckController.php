<?php

require_once __DIR__ . '/BaseController.php';

class PrintCheckController extends BaseController
{
    private PrintCheckUseCase $useCase;

    public function __construct(PrintCheckUseCase $useCase, LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->useCase = $useCase;
    }

    protected function validateRequest(array $request): array
    {
        $required = ['tableData', 'cashier', 'payments', 'type', 'taxationSystem'];
        
        foreach ($required as $field) {
            if (!isset($request[$field])) {
                throw new ValidationException("Отсутствует обязательное поле: {$field}");
            }
        }

        // Валидация tableData
        if (!is_array($request['tableData']) || empty($request['tableData'])) {
            throw new ValidationException("tableData должен быть непустым массивом");
        }

        // Валидация payments
        if (!is_array($request['payments']) || empty($request['payments'])) {
            throw new ValidationException("payments должен быть непустым массивом");
        }

        // Валидация типа операции
        if (!in_array($request['type'], ['sell', 'return'])) {
            throw new ValidationException("type должен быть 'sell' или 'return'");
        }

        return $request;
    }

    protected function executeUseCase(array $request): array
    {
        $check = Check::fromArray($request);
        $result = $this->useCase->execute($check);
        
        if (!$result->success && $result->message) {
            throw new BusinessLogicException($result->message);
        }

        return [
            'success' => $result->success,
            'message' => $result->message,
            'printed_lines' => $result->printedLines ?? []
        ];
    }
}
