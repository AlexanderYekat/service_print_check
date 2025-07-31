<?php
/**
 * Консольная утилита для управления номером фискального накопителя
 * 
 * Использование:
 * php FiscalDriveNumberManager.php show          - показать текущий номер ФН
 * php FiscalDriveNumberManager.php reset         - сбросить кэш номера ФН
 * php FiscalDriveNumberManager.php update        - принудительно обновить номер ФН с ККТ
 * php FiscalDriveNumberManager.php set <number>  - установить номер ФН вручную
 */

require_once __DIR__ . '/../infrastructure/settings_storage/JsonFileSettingsStorage.php';
require_once __DIR__ . '/../infrastructure/printer/SerialKktAdapter.php';
require_once __DIR__ . '/../infrastructure/logger/FileLogger.php';

class FiscalDriveNumberManager
{
    private JsonFileSettingsStorage $settingsStorage;
    private SerialKktAdapter $kktAdapter;
    private FileLogger $logger;
    private array $config;

    public function __construct()
    {
        $this->logger = new FileLogger(__DIR__ . '/../../logs/fiscal_drive_number.log');
        $this->settingsStorage = new JsonFileSettingsStorage(__DIR__ . '/../../config/settings.json');
        $this->config = $this->settingsStorage->load();
        
        // Инициализируем ККТ адаптер
        $this->kktAdapter = new SerialKktAdapter(
            $this->config['printer']['com_class'] ?? 'AddIn.Fptr10',
            $this->config['printer']['com_port'] ?? 'COM1',
            $this->config['printer']['emulation'] ?? true
        );
    }

    /**
     * Главная точка входа
     */
    public function run(array $args): void
    {
        if (count($args) < 2) {
            $this->showUsage();
            return;
        }

        $command = $args[1];

        try {
            switch ($command) {
                case 'show':
                    $this->showFiscalDriveNumber();
                    break;
                case 'reset':
                    $this->resetFiscalDriveNumber();
                    break;
                case 'update':
                    $this->updateFiscalDriveNumber();
                    break;
                case 'set':
                    if (!isset($args[2])) {
                        echo "Ошибка: не указан номер ФН для установки\n";
                        $this->showUsage();
                        return;
                    }
                    $this->setFiscalDriveNumber($args[2]);
                    break;
                case 'clear':
                    $this->clearFiscalDriveNumber();
                    break;
                default:
                    echo "Неизвестная команда: {$command}\n";
                    $this->showUsage();
                    break;
            }
        } catch (Exception $e) {
            echo "Ошибка: " . $e->getMessage() . "\n";
            $this->logger->error("Ошибка в FiscalDriveNumberManager: " . $e->getMessage());
        }
    }

    /**
     * Показывает текущий номер ФН
     */
    private function showFiscalDriveNumber(): void
    {
        $fnNumber = $this->settingsStorage->getFiscalDriveNumber();
        
        if ($fnNumber !== null) {
            echo "Текущий номер фискального накопителя: {$fnNumber}\n";
            echo "Источник: кэш\n";
        } else {
            echo "Номер фискального накопителя не кэширован\n";
            echo "Попробуйте команду 'update' для получения с ККТ\n";
        }
    }

    /**
     * Сбрасывает кэшированный номер ФН
     */
    private function resetFiscalDriveNumber(): void
    {
        $data = $this->settingsStorage->load();
        unset($data['fiscal_drive_number']);
        $this->settingsStorage->save($data);
        
        echo "Кэш номера фискального накопителя сброшен\n";
        $this->logger->info("FiscalDriveNumber кэш сброшен через консольную команду");
    }

    /**
     * Принудительно обновляет номер ФН с ККТ
     */
    private function updateFiscalDriveNumber(): void
    {
        echo "Получение номера фискального накопителя с ККТ устройства...\n";
        
        $fnNumber = $this->kktAdapter->readFiscalDriveNumberFromDevice();
        $this->settingsStorage->setFiscalDriveNumber($fnNumber);
        
        echo "Номер фискального накопителя обновлен: {$fnNumber}\n";
        $this->logger->info("FiscalDriveNumber обновлен с ККТ через консольную команду: {$fnNumber}");
    }

    /**
     * Устанавливает номер ФН вручную
     */
    private function setFiscalDriveNumber(string $fnNumber): void
    {
        // Простая валидация номера ФН (должен быть числовым и длиной 16 символов)
        if (!ctype_digit($fnNumber) || strlen($fnNumber) !== 16) {
            throw new Exception("Некорректный номер ФН. Должен состоять из 16 цифр");
        }

        $this->settingsStorage->setFiscalDriveNumber($fnNumber);
        
        echo "Номер фискального накопителя установлен: {$fnNumber}\n";
        $this->logger->info("FiscalDriveNumber установлен вручную через консольную команду: {$fnNumber}");
    }

    private function clearFiscalDriveNumber(): void
    {
        $this->settingsStorage->clearFiscalDriveNumber();
        echo "Кэш номера фискального накопителя очищен\n";
        $this->logger->info("FiscalDriveNumber кэш очищен вручную");
    }

    /**
     * Показывает справку по использованию
     */
    private function showUsage(): void
    {
        echo "Управление номером фискального накопителя\n";
        echo "========================================\n";
        echo "Использование: php FiscalDriveNumberManager.php <команда> [параметры]\n\n";
        echo "Команды:\n";
        echo "  show          - показать текущий номер ФН\n";
        echo "  reset         - сбросить кэш номера ФН\n";
        echo "  update        - принудительно обновить номер ФН с ККТ\n";
        echo "  set <number>  - установить номер ФН вручную (16 цифр)\n\n";
        echo "Примеры:\n";
        echo "  php FiscalDriveNumberManager.php show\n";
        echo "  php FiscalDriveNumberManager.php update\n";
        echo "  php FiscalDriveNumberManager.php set 9999078900000961\n";
    }
}

// Запуск если файл вызван напрямую
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $manager = new FiscalDriveNumberManager();
    $manager->run($argv);
}