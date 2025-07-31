<?php

require_once __DIR__ . '/../domain/service/PermitMarkCheckUseCase.php';
require_once __DIR__ . '/../infrastructure/honest_sign/HttpPermitMarkCheckGateway.php';
require_once __DIR__ . '/../domain/model/MarkingCode.php';
require_once __DIR__ . '/../infrastructure/logger/FileLogger.php';
require_once __DIR__ . '/../infrastructure/printer/SerialKktAdapter.php';
require_once __DIR__ . '/../infrastructure/settings_storage/JsonFileSettingsStorage.php';

/**
 * Контроллер для синхронной проверки марки в разрешительном режиме
 */
class PermitMarkCheckController
{
    private PermitMarkCheckUseCase $useCase;
    private FileLogger $logger;
    private JsonFileSettingsStorage $settingsStorage;
    private SerialKktAdapter $kktAdapter;
    private array $config;

    public function __construct()
    {
        $this->logger = new FileLogger('logs/api.log');
        
        // Загружаем настройки
        $this->settingsStorage = new JsonFileSettingsStorage(__DIR__ . '/../../config/settings.json');
        $this->config = $this->settingsStorage->load();
        
        // Инициализируем ККТ адаптер для получения fiscalDriveNumber
        $this->kktAdapter = new SerialKktAdapter(
            $this->config['printer']['com_class'] ?? 'AddIn.Fptr10',
            $this->config['printer']['com_port'] ?? 'COM1',
            $this->config['printer']['emulation'] ?? true
        );
        
        // Инициализируем зависимости
        $gateway = new HttpPermitMarkCheckGateway(
            $this->config['honest_sign']['api_url'] ?? 'https://api.markirovka.ru',
            $this->config['honest_sign']['x-api-token'] ?? ''
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

            $markingCode = new MarkingCode($request['marking_code']);

            // Формируем контекст для передачи всех дополнительных параметров
            $context = [];
            
            // Добавляем ИНН и GTIN в контекст (согласно ТЗ не храним в доменной модели)
            if (!empty($request['inn'])) {
                $context['inn'] = $request['inn'];
            }
            if (!empty($request['gtin'])) {
                $context['gtin'] = $request['gtin'];
            }
            
            // Проверяем, нужно ли включать fiscalDriveNumber
            if ($this->config['honest_sign']['includeFiscalDriveNumberInMarkCheck'] ?? false) {
                $fiscalDriveNumber = $this->getFiscalDriveNumber();
                if ($fiscalDriveNumber !== null) {
                    $context['fiscalDriveNumber'] = $fiscalDriveNumber;
                    $this->logger->info("FiscalDriveNumber добавлен в контекст проверки марки: {$fiscalDriveNumber}");
                }
            }

            $result = $this->useCase->execute($markingCode, $context);

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
     * Получает номер фискального накопителя из кэша или ККТ
     * @return string|null Номер ФН или null в случае ошибки
     */
    private function getFiscalDriveNumber(): ?string
    {
        try {
            // Сначала проверяем кэш
            $cachedFnNumber = $this->settingsStorage->getFiscalDriveNumber();
            if ($cachedFnNumber !== null) {
                $this->logger->info("FiscalDriveNumber получен из кэша: {$cachedFnNumber}");
                return $cachedFnNumber;
            }

            // Если в кэше нет - читаем с ККТ
            $this->logger->info("FiscalDriveNumber не найден в кэше, читаем с ККТ устройства");
            $fnNumber = $this->kktAdapter->readFiscalDriveNumberFromDevice();
            
            // Сохраняем в кэш
            $this->settingsStorage->setFiscalDriveNumber($fnNumber);
            $this->logger->info("FiscalDriveNumber получен с ККТ и сохранен в кэш: {$fnNumber}");
            
            return $fnNumber;

        } catch (Exception $e) {
            $this->logger->error("Ошибка получения FiscalDriveNumber: " . $e->getMessage());
            return null;
        }
    }
}