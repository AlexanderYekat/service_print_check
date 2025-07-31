<?php

namespace App\Api;

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../domain/service/PermitMarkCheckUseCase.php';
require_once __DIR__ . '/../domain/model/MarkingCode.php';
require_once __DIR__ . '/../infrastructure/printer/SerialKktAdapter.php';
require_once __DIR__ . '/../../JsonFileSettingsStorage.php';

use App\Api\BaseController;
use App\Domain\Service\PermitMarkCheckUseCase;
use App\Infrastructure\Printer\SerialKktAdapter;
// Остальные классы пока в глобальном namespace

/**
 * Контроллер для синхронной проверки марки в разрешительном режиме
 */
class PermitMarkCheckController extends BaseController
{
    private PermitMarkCheckUseCase $useCase;
    private \JsonFileSettingsStorage $settingsStorage;
    private SerialKktAdapter $kktAdapter;
    private array $config;

    public function __construct(
        PermitMarkCheckUseCase $useCase,
        \JsonFileSettingsStorage $settingsStorage,
        SerialKktAdapter $kktAdapter,
        array $config,
        \LoggerInterface $logger
    ) {
        parent::__construct($logger);
        $this->useCase = $useCase;
        $this->settingsStorage = $settingsStorage;
        $this->kktAdapter = $kktAdapter;
        $this->config = $config;
    }

    protected function validateRequest(array $request): array
    {
        if (!isset($request['marking_code'])) {
            throw new ValidationException('Не указан код маркировки');
        }
        return $request;
    }

    protected function executeUseCase(array $request): array
    {
        $markingCode = new MarkingCode($request['marking_code']);

        // Формируем контекст для передачи всех дополнительных параметров
        $context = [];
        
        
        // Проверяем, нужно ли включать fiscalDriveNumber
        if ($this->config['honest_sign']['includeFiscalDriveNumberInMarkCheck'] ?? false) {
            $fiscalDriveNumber = $this->getFiscalDriveNumber();
            if ($fiscalDriveNumber !== null) {
                $context['fiscalDriveNumber'] = $fiscalDriveNumber;
                $this->logger->info("FiscalDriveNumber добавлен в контекст проверки марки: {$fiscalDriveNumber}");
            }
        }

        $result = $this->useCase->execute($markingCode, $context);

        if (!$result->success && $result->error) {
            throw new BusinessLogicException($result->error);
        }

        return $result->getData();
    }

    /**
     * Синхронная проверка марки в разрешительном режиме
     * POST /api/permit-mark-check
     */
    public function checkPermit(): void
    {
        $request = $this->getJsonInput();
        $this->handle($request);
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