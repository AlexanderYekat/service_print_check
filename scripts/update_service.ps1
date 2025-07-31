# Скрипт для обновления службы CloudPosBridgePHP (НОВАЯ АРХИТЕКТУРА)
# Соответствует чистой архитектуре с разделением по слоям

# Указываем путь к корневой папке проекта (один уровень выше scripts)
$ProjectSourcePath = Split-Path -Parent (Get-Item -Path $PSScriptRoot).FullName

# Указываем путь к папке установки службы (НОВАЯ АРХИТЕКТУРА: корень приложения)
$ServiceInstallPath = "C:\CloudPosBridgePHP"

# Настройка логирования согласно ТЗ
$LogFile = "$ServiceInstallPath\logs\update_service.log"

function Write-LogMessage {
    param([string]$Message, [string]$Level = "INFO")
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logMessage = "[$timestamp] [$Level] $Message"
    Write-Host $logMessage
    if (Test-Path (Split-Path $LogFile)) {
        Add-Content -Path $LogFile -Value $logMessage
    }
}

Write-LogMessage "Начало процесса обновления CloudPosBridgePHP (новая архитектура)"
Write-LogMessage "Путь к проекту: $ProjectSourcePath"
Write-LogMessage "Путь установки: $ServiceInstallPath"

Write-LogMessage "Остановка службы CloudPosBridgeServicePHP..."
try {
    Stop-Service -Name CloudPosBridgeServicePHP -ErrorAction Stop
    Write-LogMessage "Служба CloudPosBridgeServicePHP остановлена."
}
catch {
    Write-LogMessage "Не удалось остановить службу CloudPosBridgeServicePHP. Возможно, она уже остановлена или не существует. Продолжаем." "WARNING"
}

Write-LogMessage "Копирование файлов проекта в [$ServiceInstallPath] (новая архитектура)..."
try {
    # Создаем целевую папку, если она не существует
    if (-not (Test-Path -Path $ServiceInstallPath)) {
        New-Item -Path $ServiceInstallPath -ItemType Directory -Force | Out-Null
        Write-LogMessage "Создана целевая папка: $ServiceInstallPath"
    }

    # Создаем папку логов если её нет
    if (-not (Test-Path -Path "$ServiceInstallPath\logs")) {
        New-Item -Path "$ServiceInstallPath\logs" -ItemType Directory -Force | Out-Null
        Write-LogMessage "Создана папка логов: $ServiceInstallPath\logs"
    }

    # === НОВАЯ АРХИТЕКТУРА: Чистое разделение по слоям ===
    
    # Копируем src (бизнес-логика, адаптеры, контроллеры)
    $srcPath = Join-Path $ProjectSourcePath "src"
    if (Test-Path -Path $srcPath) {
        Write-LogMessage "Копирование src (бизнес-логика)..."
        Copy-Item -Path $srcPath -Destination $ServiceInstallPath -Recurse -Force
        Write-LogMessage "Папка src скопирована."
    } else {
        Write-LogMessage "Папка src не найдена: $srcPath" "ERROR"
        throw "Критическая папка src отсутствует!"
    }

    # Копируем public (точка входа для Web/API)
    $publicPath = Join-Path $ProjectSourcePath "public"
    if (Test-Path -Path $publicPath) {
        Write-LogMessage "Копирование public (точка входа)..."
        Copy-Item -Path $publicPath -Destination $ServiceInstallPath -Recurse -Force
        Write-LogMessage "Папка public скопирована."
    } else {
        Write-LogMessage "Папка public не найдена: $publicPath" "ERROR"
        throw "Критическая папка public отсутствует!"
    }

    # Копируем config (только если пользователь не настроил свой)
    $configPath = Join-Path $ProjectSourcePath "config"
    $targetConfigPath = Join-Path $ServiceInstallPath "config"
    if (Test-Path -Path $configPath) {
        if (-not (Test-Path -Path $targetConfigPath)) {
            Write-LogMessage "Копирование config (первая установка)..."
            Copy-Item -Path $configPath -Destination $ServiceInstallPath -Recurse -Force
            Write-LogMessage "Папка config скопирована."
        } else {
            Write-LogMessage "Папка config уже существует - сохраняем пользовательские настройки" "WARNING"
            # Копируем только новые файлы конфигурации, не перезаписывая существующие
            Get-ChildItem -Path $configPath -Recurse | ForEach-Object {
                $relativePath = $_.FullName.Substring($configPath.Length + 1)
                $targetFile = Join-Path $targetConfigPath $relativePath
                if (-not (Test-Path -Path $targetFile)) {
                    Write-LogMessage "Копирование нового файла конфигурации: $relativePath"
                    $targetDir = Split-Path $targetFile
                    if (-not (Test-Path -Path $targetDir)) {
                        New-Item -Path $targetDir -ItemType Directory -Force | Out-Null
                    }
                    Copy-Item -Path $_.FullName -Destination $targetFile -Force
                }
            }
        }
    }

    # Копируем bank (Go-бинарь и temp-файлы)
    $bankPath = Join-Path $ProjectSourcePath "bank"
    if (Test-Path -Path $bankPath) {
        Write-LogMessage "Копирование bank (Go-программа)..."
        Copy-Item -Path $bankPath -Destination $ServiceInstallPath -Recurse -Force
        Write-LogMessage "Папка bank скопирована."
    }

    # Копируем scripts (служебные скрипты)
    $scriptsPath = Join-Path $ProjectSourcePath "scripts"
    if (Test-Path -Path $scriptsPath) {
        Write-LogMessage "Копирование scripts (служебные скрипты)..."
        Copy-Item -Path $scriptsPath -Destination $ServiceInstallPath -Recurse -Force
        Write-LogMessage "Папка scripts скопирована."
    }

    # Копируем vendor (PHP-зависимости Composer)
    $vendorPath = Join-Path $ProjectSourcePath "vendor"
    if (Test-Path -Path $vendorPath) {
        Write-LogMessage "Копирование vendor (PHP-зависимости)..."
        Copy-Item -Path $vendorPath -Destination $ServiceInstallPath -Recurse -Force
        Write-LogMessage "Папка vendor скопирована."
    }

    # === ДОПОЛНИТЕЛЬНЫЕ ФАЙЛЫ ===
    
    # Копируем README.md в корень
    $readmePath = Join-Path $ProjectSourcePath "README.md"
    if (Test-Path -Path $readmePath) {
        Copy-Item -Path $readmePath -Destination $ServiceInstallPath -Force
        Write-LogMessage "README.md скопирован."
    }

    # Копируем дополнительные папки
    $additionalFolders = @("templates", "resource", "samples")
    foreach ($folder in $additionalFolders) {
        $source = Join-Path $ProjectSourcePath $folder
        if (Test-Path -Path $source) {
            Write-LogMessage "Копирование дополнительной папки [$folder]..."
            Copy-Item -Path $source -Destination $ServiceInstallPath -Recurse -Force
            Write-LogMessage "Папка [$folder] скопирована."
        } else {
            Write-LogMessage "Дополнительная папка [$folder] не найдена: $source" "WARNING"
        }
    }

    Write-LogMessage "Все необходимые файлы скопированы (новая архитектура)."
}
catch {
    Write-LogMessage "Ошибка при копировании файлов: $_" "ERROR"
    exit 1
}

# === ПРОВЕРКА КОРРЕКТНОСТИ СТРУКТУРЫ (согласно ТЗ) ===
Write-LogMessage "Проверка корректности структуры после обновления..."

$requiredFolders = @("src", "public", "config", "bank", "scripts", "logs")
$missingFolders = @()

foreach ($folder in $requiredFolders) {
    $folderPath = Join-Path $ServiceInstallPath $folder
    if (-not (Test-Path -Path $folderPath)) {
        $missingFolders += $folder
        Write-LogMessage "КРИТИЧЕСКАЯ ОШИБКА: Отсутствует папка $folder" "ERROR"
    } else {
        Write-LogMessage "✓ Папка $folder присутствует"
    }
}

# Проверяем критические файлы
$criticalFiles = @(
    "public\index.php",
    "config\settings.json",
    "src\bootstrap.php"
)

foreach ($file in $criticalFiles) {
    $filePath = Join-Path $ServiceInstallPath $file
    if (-not (Test-Path -Path $filePath)) {
        Write-LogMessage "КРИТИЧЕСКАЯ ОШИБКА: Отсутствует файл $file" "ERROR"
        $missingFolders += "file:$file"
    } else {
        Write-LogMessage "✓ Критический файл $file присутствует"
    }
}

if ($missingFolders.Count -gt 0) {
    Write-LogMessage "ФАТАЛЬНАЯ ОШИБКА: Структура не соответствует новой архитектуре!" "ERROR"
    Write-LogMessage "Отсутствующие компоненты: $($missingFolders -join ', ')" "ERROR"
    exit 1
} else {
    Write-LogMessage "✓ Структура проекта соответствует новой архитектуре"
}

# === ЗАПУСК СЛУЖБЫ ===
Write-LogMessage "Запуск службы CloudPosBridgeServicePHP..."
try {
    Start-Service -Name CloudPosBridgeServicePHP -ErrorAction Stop
    Write-LogMessage "✓ Служба CloudPosBridgeServicePHP запущена."
}
catch {
    Write-LogMessage "Не удалось запустить службу CloudPosBridgeServicePHP: $_" "ERROR"
    exit 1
}

Write-LogMessage "✓ Процесс обновления завершен успешно (новая архитектура)."
Write-LogMessage "Веб-интерфейс доступен по адресу: http://localhost:8000/"
Write-LogMessage "Логи службы находятся в: $ServiceInstallPath\logs\" 