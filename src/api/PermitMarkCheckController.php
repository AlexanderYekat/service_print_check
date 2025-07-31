<?php

require_once __DIR__ . '/../domain/service/PermitMarkCheckUseCase.php';
require_once __DIR__ . '/../infrastructure/honest_sign/HttpPermitMarkCheckGateway.php';
require_once __DIR__ . '/../domain/model/MarkingCode.php';
require_once __DIR__ . '/../infrastructure/logger/FileLogger.php';

/**
 * Контроллер для синхронной проверки марки в разрешительном режиме
 */
class PermitMarkCheckController
{
    private PermitMarkCheckUseCase $useCase;
    private FileLogger $logger;

    public function __construct()
    {
        $this->logger = new FileLogger('logs/api.log');
        
        // Загружаем настройки из файла или переменных окружения
        $settings = $this->loadSettings();
        
        // Инициализируем зависимости
        $gateway = new HttpPermitMarkCheckGateway(
            $settings['honest_sign_api_url'] ?? 'https://api.markirovka.ru',
            $settings['honest_sign_api_key'] ?? ''
        );
        
        $this->useCase = new PermitMarkCheckUseCase($gateway);
    }

    /**
     * Синхронная проверка марки в разрешительном режиме
     * POST /api/permit-mark-check
     */
    public function checkPermit(): void
    {
        try {
            $request = $this->getJsonInput();
            
            if (!isset($request['marking_code'])) {
                $this->sendError('Не указан код маркировки', 400);
                return;
            }

            $markingCode = new MarkingCode(
                $request['marking_code'],
                $request['inn'] ?? null,
                $request['gtin'] ?? null
            );

            $result = $this->useCase->execute($markingCode);

            if ($result->success) {
                $this->sendSuccess($result->getData(), $result->message);
            } else {
                $this->sendError($result->error, 400, $result->getData());
            }

        } catch (Exception $e) {
            $this->logger->error("Ошибка проверки марки в разрешительном режиме: " . $e->getMessage());
            $this->sendError('Внутренняя ошибка сервера', 500);
        }
    }

    /**
     * Получает JSON данные из тела запроса
     */
    private function getJsonInput(): array
    {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        return $data ?? [];
    }

    /**
     * Отправляет успешный ответ
     */
    private function sendSuccess(array $data, ?string $message = null): void
    {
        $response = [
            'success' => true,
            'data' => $data,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $this->sendJsonResponse($response, 200);
    }

    /**
     * Отправляет ответ с ошибкой
     */
    private function sendError(string $error, int $statusCode = 500, ?array $data = null): void
    {
        $response = [
            'success' => false,
            'error' => $error,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        $this->sendJsonResponse($response, $statusCode);
    }

    /**
     * Отправляет JSON ответ
     */
    private function sendJsonResponse(array $data, int $statusCode): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Загружает настройки
     */
    private function loadSettings(): array
    {
        $settingsFile = __DIR__ . '/../../settings_storage/settings.json';
        if (file_exists($settingsFile)) {
            $content = file_get_contents($settingsFile);
            return json_decode($content, true) ?? [];
        }
        return [];
    }
}