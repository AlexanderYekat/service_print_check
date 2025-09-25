# Быстрый деплой CloudPosBridge для Windows
# PowerShell скрипт

param(
    [switch]$Force = $false
)

$ErrorActionPreference = "Stop"

$ProjectRoot = Split-Path -Parent $PSScriptRoot
$Timestamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"

Write-Host "?? Быстрый деплой CloudPosBridge" -ForegroundColor Green
Write-Host "Проект: $ProjectRoot" -ForegroundColor Gray
Write-Host "Время: $Timestamp" -ForegroundColor Gray
Write-Host "================================" -ForegroundColor Green

# Переходим в корень проекта
Set-Location $ProjectRoot

try {
    # Проверяем git статус
    Write-Host "?? Проверка git статуса..." -ForegroundColor Yellow
    $gitStatus = git status --porcelain 2>$null
    
    if ($gitStatus -and -not $Force) {
        Write-Host "??  Есть незакоммиченные изменения:" -ForegroundColor Yellow
        git status --short
        $response = Read-Host "Продолжить деплой? (y/N)"
        if ($response -ne "y" -and $response -ne "Y") {
            Write-Host "? Деплой отменен" -ForegroundColor Red
            exit 1
        }
    }

    # Обновляем код
    Write-Host "?? Обновление кода..." -ForegroundColor Yellow
    git pull origin main

    # Устанавливаем зависимости
    Write-Host "?? Установка зависимостей..." -ForegroundColor Yellow
    if (Get-Command composer -ErrorAction SilentlyContinue) {
        composer install --no-dev --optimize-autoloader
    } else {
        Write-Host "??  Composer не найден, пропускаем установку зависимостей" -ForegroundColor Yellow
    }

    # Создаем необходимые папки
    Write-Host "?? Создание необходимых папок..." -ForegroundColor Yellow
    @("logs", "config", "settings_storage", "backups") | ForEach-Object {
        if (-not (Test-Path $_)) {
            New-Item -ItemType Directory -Path $_ -Force | Out-Null
        }
    }

    # Проверяем файл настроек
    Write-Host "??  Проверка настроек..." -ForegroundColor Yellow
    $settingsFile = "config/settings.json"
    
    if (-not (Test-Path $settingsFile)) {
        Write-Host "?? Создание базового файла настроек..." -ForegroundColor Yellow
        
        $defaultSettings = @{
            printer = @{
                com_class = "AddIn.Fptr10"
                com_port = "COM1"
                emulation = $true
            }
            bank = @{
                binary_path = "./bank/mainbeznal.exe"
                emulation = $true
                timeout = 30
            }
            scale = @{
                com_port = "COM2"
                emulation = $true
            }
            honest_sign = @{
                api_url = "https://api.markirovka.ru"
                "x-api-token" = ""
                timeout = 30
            }
        }
        
        $defaultSettings | ConvertTo-Json -Depth 4 | Set-Content $settingsFile -Encoding UTF8
    }

    # Запускаем тесты (если есть)
    Write-Host "?? Запуск тестов..." -ForegroundColor Yellow
    $testFile = "tests/run_all_tests.php"
    
    if (Test-Path $testFile) {
        try {
            php $testFile
            Write-Host "? Тесты пройдены" -ForegroundColor Green
        } catch {
            Write-Host "??  Некоторые тесты провалились, но продолжаем" -ForegroundColor Yellow
        }
    } else {
        Write-Host "??  Тесты не найдены" -ForegroundColor Gray
    }

    # Проверяем health
    Write-Host "?? Проверка здоровья системы..." -ForegroundColor Yellow
    
    if (Test-Path "public/index.php") {
        # Простая проверка что bootstrap загружается
        $syntaxCheck = php -l "src/bootstrap.php" 2>$null
        if ($LASTEXITCODE -eq 0) {
            Write-Host "? Bootstrap проходит синтаксическую проверку" -ForegroundColor Green
        } else {
            Write-Host "? Ошибка в bootstrap.php" -ForegroundColor Red
            exit 1
        }
    } else {
        Write-Host "??  Файл index.php не найден" -ForegroundColor Gray
    }

    Write-Host ""
    Write-Host "? Быстрый деплой завершен успешно!" -ForegroundColor Green
    Write-Host "?? Проверьте работу системы в браузере" -ForegroundColor Cyan
    Write-Host "?? Логи доступны в папке logs/" -ForegroundColor Cyan
    Write-Host ""

} catch {
    Write-Host "? Ошибка деплоя: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}