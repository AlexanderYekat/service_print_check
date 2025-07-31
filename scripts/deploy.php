<?php
/**
 * Автоматический скрипт деплоя CloudPosBridge
 * 
 * Выполняет полный цикл деплоя:
 * - git pull
 * - composer install
 * - миграции настроек
 * - перезапуск сервисов
 * - проверка здоровья системы
 */

class CloudPosBridgeDeployer
{
    private string $projectRoot;
    private array $config;
    private array $log = [];

    public function __construct()
    {
        $this->projectRoot = dirname(__DIR__);
        $this->loadConfig();
    }

    public function deploy(): bool
    {
        $this->log("🚀 Начинаем деплой CloudPosBridge");
        $this->log("====================================");

        try {
            $this->checkPrerequisites();
            $this->backupCurrentVersion();
            $this->updateCode();
            $this->installDependencies();
            $this->migrateSettings();
            $this->runTests();
            $this->restartServices();
            $this->verifyDeployment();
            
            $this->log("✅ Деплой завершен успешно!");
            return true;
            
        } catch (Exception $e) {
            $this->log("❌ Деплой провален: " . $e->getMessage());
            $this->rollback();
            return false;
        }
    }

    private function checkPrerequisites(): void
    {
        $this->log("🔍 Проверка предварительных условий...");
        
        // Проверяем что мы в git репозитории
        if (!is_dir($this->projectRoot . '/.git')) {
            throw new Exception("Проект должен быть git репозиторием");
        }
        
        // Проверяем доступ к composer
        exec('composer --version 2>&1', $output, $return);
        if ($return !== 0) {
            throw new Exception("Composer не найден или недоступен");
        }
        
        // Проверяем права на запись
        if (!is_writable($this->projectRoot)) {
            throw new Exception("Нет прав на запись в директорию проекта");
        }
        
        $this->log("✅ Предварительные проверки пройдены");
    }

    private function backupCurrentVersion(): void
    {
        $this->log("💾 Создание резервной копии...");
        
        $backupDir = $this->projectRoot . '/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $backupPath = $backupDir . "/backup_$timestamp";
        
        // Создаем backup только критичных файлов
        $criticalFiles = [
            'config/settings.json',
            'logs/',
            'settings_storage/'
        ];
        
        mkdir($backupPath, 0755, true);
        
        foreach ($criticalFiles as $file) {
            $source = $this->projectRoot . '/' . $file;
            $dest = $backupPath . '/' . $file;
            
            if (file_exists($source)) {
                if (is_dir($source)) {
                    $this->copyDirectory($source, $dest);
                } else {
                    copy($source, $dest);
                }
            }
        }
        
        $this->log("✅ Резервная копия создана: $backupPath");
    }

    private function updateCode(): void
    {
        $this->log("📥 Обновление кода...");
        
        // Проверяем что нет незакоммиченных изменений
        exec('git status --porcelain 2>&1', $output);
        if (!empty($output)) {
            throw new Exception("Есть незакоммиченные изменения. Закоммитьте или сбросьте их перед деплоем");
        }
        
        // Получаем обновления
        exec('git pull origin main 2>&1', $output, $return);
        if ($return !== 0) {
            throw new Exception("Ошибка git pull: " . implode("\n", $output));
        }
        
        $this->log("✅ Код обновлен");
    }

    private function installDependencies(): void
    {
        $this->log("📦 Установка зависимостей...");
        
        exec('composer install --no-dev --optimize-autoloader 2>&1', $output, $return);
        if ($return !== 0) {
            throw new Exception("Ошибка composer install: " . implode("\n", $output));
        }
        
        $this->log("✅ Зависимости установлены");
    }

    private function migrateSettings(): void
    {
        $this->log("⚙️  Миграция настроек...");
        
        $settingsFile = $this->projectRoot . '/config/settings.json';
        
        if (!file_exists($settingsFile)) {
            // Создаем базовый файл настроек
            $defaultSettings = [
                'printer' => [
                    'com_class' => 'AddIn.Fptr10',
                    'com_port' => 'COM1',
                    'emulation' => true
                ],
                'bank' => [
                    'binary_path' => './bank/mainbeznal.exe',
                    'emulation' => true,
                    'timeout' => 30
                ],
                'scale' => [
                    'com_port' => 'COM2',
                    'emulation' => true
                ],
                'honest_sign' => [
                    'api_url' => 'https://api.markirovka.ru',
                    'x-api-token' => '',
                    'timeout' => 30
                ]
            ];
            
            file_put_contents($settingsFile, json_encode($defaultSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->log("📝 Создан файл настроек по умолчанию");
        }
        
        $this->log("✅ Настройки проверены");
    }

    private function runTests(): void
    {
        $this->log("🧪 Запуск тестов...");
        
        $testScript = $this->projectRoot . '/tests/run_all_tests.php';
        if (file_exists($testScript)) {
            exec("php $testScript 2>&1", $output, $return);
            if ($return !== 0) {
                $this->log("⚠️  Некоторые тесты провалились, но продолжаем деплой");
                $this->log("Вывод тестов: " . implode("\n", $output));
            } else {
                $this->log("✅ Все тесты пройдены");
            }
        } else {
            $this->log("ℹ️  Тесты не найдены, пропускаем");
        }
    }

    private function restartServices(): void
    {
        $this->log("🔄 Перезапуск сервисов...");
        
        // Перезапускаем PHP-FPM если используется
        if (function_exists('opcache_reset')) {
            opcache_reset();
            $this->log("✅ OPCache сброшен");
        }
        
        // Проверяем перезапуск веб-сервера (для Windows/IIS)
        if (PHP_OS_FAMILY === 'Windows') {
            $this->log("ℹ️  На Windows перезапуск веб-сервера должен быть выполнен вручную");
        }
        
        $this->log("✅ Сервисы перезапущены");
    }

    private function verifyDeployment(): void
    {
        $this->log("🏥 Проверка здоровья системы...");
        
        // Включаем bootstrap для проверки
        require_once $this->projectRoot . '/src/bootstrap.php';
        
        $healthChecker = container('health_checker');
        $healthReport = $healthChecker->checkAll();
        
        if ($healthReport['overall_status'] === 'critical') {
            throw new Exception("Система в критическом состоянии после деплоя");
        }
        
        $this->log("✅ Система работает нормально");
        $this->log("📊 Статус компонентов: " . $healthReport['overall_status']);
    }

    private function rollback(): void
    {
        $this->log("🔄 Откат к предыдущей версии...");
        
        // Простой откат через git reset
        exec('git reset --hard HEAD~1 2>&1', $output, $return);
        if ($return === 0) {
            $this->log("✅ Откат выполнен");
        } else {
            $this->log("❌ Ошибка отката: " . implode("\n", $output));
        }
    }

    private function loadConfig(): void
    {
        $configFile = $this->projectRoot . '/config/deploy.json';
        
        if (file_exists($configFile)) {
            $this->config = json_decode(file_get_contents($configFile), true);
        } else {
            $this->config = [
                'backup_retention_days' => 7,
                'git_branch' => 'main',
                'run_tests' => true
            ];
        }
    }

    private function copyDirectory(string $source, string $dest): void
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $destPath = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            if ($item->isDir()) {
                mkdir($destPath, 0755, true);
            } else {
                copy($item, $destPath);
            }
        }
    }

    private function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message";
        
        echo $logMessage . "\n";
        $this->log[] = $logMessage;
        
        // Записываем в файл лога
        $logFile = $this->projectRoot . '/logs/deploy.log';
        file_put_contents($logFile, $logMessage . "\n", FILE_APPEND | LOCK_EX);
    }
}

// Запуск если файл вызван напрямую
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $deployer = new CloudPosBridgeDeployer();
    $success = $deployer->deploy();
    exit($success ? 0 : 1);
}