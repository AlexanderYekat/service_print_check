# Скрипт для тестирования новой архитектуры CloudPosBridge
# Чек-лист согласно ТЗ

$ServiceInstallPath = "C:\CloudPosBridgePHP"

Write-Host "=== ЧЕК-ЛИСТ ТЕСТИРОВАНИЯ НОВОЙ АРХИТЕКТУРЫ CLOUDPOSBRIDGE ===" -ForegroundColor Green
Write-Host ""

# Проверка 1: Структура после установки строго соответствует описанию
Write-Host "1. Проверка структуры папок..." -ForegroundColor Yellow
$requiredFolders = @("src", "public", "config", "bank", "scripts", "vendor", "logs")
$folderCheck = $true

foreach ($folder in $requiredFolders) {
    $folderPath = Join-Path $ServiceInstallPath $folder
    if (Test-Path -Path $folderPath) {
        Write-Host "  ✓ $folder - OK" -ForegroundColor Green
    } else {
        Write-Host "  ✗ $folder - ОТСУТСТВУЕТ" -ForegroundColor Red
        $folderCheck = $false
    }
}

# Проверка 2: Нет дублирующих/лишних/устаревших файлов
Write-Host "2. Проверка отсутствия устаревших файлов..." -ForegroundColor Yellow
$oldPaths = @("$ServiceInstallPath\app", "$ServiceInstallPath\*.php")
$oldFilesFound = $false

foreach ($oldPath in $oldPaths) {
    if (Test-Path -Path $oldPath) {
        Write-Host "  ✗ Найден устаревший файл/папка: $oldPath" -ForegroundColor Red
        $oldFilesFound = $true
    }
}

if (-not $oldFilesFound) {
    Write-Host "  ✓ Устаревшие файлы отсутствуют" -ForegroundColor Green
}

# Проверка 3: Все ярлыки и задачи планировщика ведут на актуальные точки
Write-Host "3. Проверка ярлыков..." -ForegroundColor Yellow
$desktopShortcut = "$env:USERPROFILE\Desktop\Настройки CloudPosBridgePHP.url"
if (Test-Path -Path $desktopShortcut) {
    $shortcutContent = Get-Content -Path $desktopShortcut -ErrorAction SilentlyContinue
    if ($shortcutContent -match "localhost:8000") {
        Write-Host "  ✓ Ярлык на рабочем столе корректный" -ForegroundColor Green
    } else {
        Write-Host "  ✗ Ярлык на рабочем столе некорректный" -ForegroundColor Red
    }
} else {
    Write-Host "  ! Ярлык на рабочем столе не найден (возможно, не создавался)" -ForegroundColor Yellow
}

# Проверка 4: Служба Windows поднимается с правильной рабочей директорией
Write-Host "4. Проверка службы Windows..." -ForegroundColor Yellow
try {
    $serviceStatus = Get-Service -Name CloudPosBridgeServicePHP -ErrorAction Stop
    Write-Host "  ✓ Служба найдена: $($serviceStatus.Status)" -ForegroundColor Green
    
    # Проверяем настройки NSSM
    $nssmPath = "$ServiceInstallPath\nssm\nssm.exe"
    if (Test-Path -Path $nssmPath) {
        $appDir = & $nssmPath get CloudPosBridgeServicePHP AppDirectory 2>$null
        $expectedDir = "$ServiceInstallPath\public"
        if ($appDir -eq $expectedDir) {
            Write-Host "  ✓ Рабочая директория корректная: $appDir" -ForegroundColor Green
        } else {
            Write-Host "  ✗ Рабочая директория некорректная: $appDir (ожидается: $expectedDir)" -ForegroundColor Red
        }
    }
} catch {
    Write-Host "  ✗ Служба не найдена или недоступна" -ForegroundColor Red
}

# Проверка 5: Конфиги сохранены
Write-Host "5. Проверка пользовательских конфигов..." -ForegroundColor Yellow
$configFile = "$ServiceInstallPath\config\settings.json"
if (Test-Path -Path $configFile) {
    Write-Host "  ✓ Файл настроек существует" -ForegroundColor Green
} else {
    Write-Host "  ✗ Файл настроек отсутствует" -ForegroundColor Red
}

# Проверка 6: Логирование работает
Write-Host "6. Проверка логирования..." -ForegroundColor Yellow
$logDir = "$ServiceInstallPath\logs"
if (Test-Path -Path $logDir) {
    $logFiles = Get-ChildItem -Path $logDir -Filter "*.log" -ErrorAction SilentlyContinue
    if ($logFiles.Count -gt 0) {
        Write-Host "  ✓ Найдено $($logFiles.Count) лог-файлов" -ForegroundColor Green
    } else {
        Write-Host "  ! Лог-файлы пока не созданы" -ForegroundColor Yellow
    }
} else {
    Write-Host "  ✗ Папка логов отсутствует" -ForegroundColor Red
}

# Проверка 7: Веб-интерфейс доступен
Write-Host "7. Проверка веб-интерфейса..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "http://localhost:8000/" -TimeoutSec 5 -ErrorAction Stop
    if ($response.StatusCode -eq 200) {
        Write-Host "  ✓ Веб-интерфейс доступен (HTTP 200)" -ForegroundColor Green
    } else {
        Write-Host "  ! Веб-интерфейс отвечает с кодом: $($response.StatusCode)" -ForegroundColor Yellow
    }
} catch {
    Write-Host "  ✗ Веб-интерфейс недоступен: $_" -ForegroundColor Red
}

Write-Host ""
Write-Host "=== ТЕСТИРОВАНИЕ ЗАВЕРШЕНО ===" -ForegroundColor Green
Write-Host "Проверьте все отмеченные ошибки (✗) и предупреждения (!) выше." -ForegroundColor Yellow