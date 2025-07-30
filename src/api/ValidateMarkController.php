<?php

require_once __DIR__ . '/../domain/service/ValidateMark.php';

class ValidateMarkController
{
    private ValidateMarkUseCase $useCase;

    public function __construct(ValidateMarkUseCase $useCase)
    {
        $this->useCase = $useCase;
    }

    public function handle(array $request): void
    {
        try {
            if (!isset($request['marking_code'])) {
                throw new Exception("Отсутствует обязательный параметр 'marking_code'");
            }

            $markingCode = new MarkingCode(
                $request['marking_code'],
                $request['inn'] ?? null,
                $request['gtin'] ?? null
            );

            $result = $this->useCase->validateMark($markingCode);

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => $result->success,
                'message' => $result->message,
                'data' => [
                    'details' => $result->details
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            
            echo json_encode([
                'success' => false,
                'message' => 'Ошибка валидации маркировки: ' . $e->getMessage()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
    }
}