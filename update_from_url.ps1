# update_from_url.ps1
# Этот скрипт предназначен для запуска PHP-службой для обновления файлов приложения из ZIP-архива по URL.

Param(
    [string]$DownloadUrl,
    [string]$LogDirPath,
    [string]$ServiceNameToStop # Имя службы для остановки/запуска
)

$LogFile = Join-Path -Path $LogDirPath -ChildPath "update_from_url.log"
$TempDir = Join-Path -Path $PSScriptRoot -ChildPath "_temp_update"
$BackupDir = Join-Path -Path $PSScriptRoot -ChildPath "_backup_$(Get-Date -Format 'yyyyMMdd_HHmmss')"

# Функция для записи сообщений в лог
function Write-Log {
    Param(
        [string]$Message
    )
    $Timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Add-Content -Path $LogFile -Value "$Timestamp - $Message"
}

# Убедитесь, что директория для логов существует
if (-not (Test-Path -Path $LogDirPath -PathType Container)) {
    New-Item -Path $LogDirPath -ItemType Directory -Force | Out-Null
}

Write-Log "Начало процесса обновления из URL: $DownloadUrl"
Write-Log "Каталог для логов: $LogDirPath"
Write-Log "Имя службы для остановки/запуска: $ServiceNameToStop"

# 1. Загрузка ZIP-файла
try {
    Write-Log "Попытка загрузки архива из $DownloadUrl..."
    # Создаем временную директорию, если она не существует
    if (Test-Path -Path $TempDir) { Remove-Item -Path $TempDir -Recurse -Force | Out-Null }
    New-Item -Path $TempDir -ItemType Directory -Force | Out-Null

    $zipFileName = Join-Path -Path $TempDir -ChildPath "update.zip"
    (New-Object System.Net.WebClient).DownloadFile($DownloadUrl, $zipFileName)
    Write-Log "Архив успешно загружен в $zipFileName."
} catch {
    Write-Log "Ошибка при загрузке архива: $($_.Exception.Message)"
    exit 1
}

# 2. Остановка службы
if (-not ([string]::IsNullOrEmpty($ServiceNameToStop))) {
    try {
        $service = Get-Service -Name $ServiceNameToStop -ErrorAction SilentlyContinue
        if ($service -and $service.Status -eq 'Running') {
            Write-Log "Остановка службы '$ServiceNameToStop'..."
            Stop-Service -Name $ServiceNameToStop -Force -ErrorAction Stop
            $service.WaitForStatus('Stopped', 60000) # Ожидаем до 60 секунд
            Write-Log "Служба '$ServiceNameToStop' успешно остановлена."
        } elseif ($service -and $service.Status -eq 'Stopped') {
            Write-Log "Служба '$ServiceNameToStop' уже остановлена."
        } else {
            Write-Log "Служба '$ServiceNameToStop' не найдена или ее статус неизвестен. Продолжаем без остановки."
        }
    } catch {
        Write-Log "Ошибка при остановке службы '$ServiceNameToStop': $($_.Exception.Message)"
        exit 1
    }
}

# 3. Создание резервной копии текущих файлов
try {
    Write-Log "Создание резервной копии текущих файлов в $BackupDir..."
    Copy-Item -Path $PSScriptRoot -Destination $BackupDir -Recurse -Exclude "_temp_update", "_backup_*", "logs", "settings" # Исключаем временные, резервные и лог-директории
    Write-Log "Резервная копия успешно создана."
} catch {
    Write-Log "Ошибка при создании резервной копии: $($_.Exception.Message)"
    # Продолжаем, так как это не критическая ошибка для самого обновления, но логируем
}

# 4. Распаковка и замена файлов
try {
    Write-Log "Распаковка архива и обновление файлов..."
    # Удаляем содержимое текущей директории, кроме временных папок, логов и настроек
    Get-ChildItem -Path $PSScriptRoot -Exclude "_temp_update", "_backup_*", "logs", "settings" | ForEach-Object { Remove-Item -Path $_.FullName -Recurse -Force | Out-Null }
    
    # Извлекаем содержимое архива непосредственно в текущую директорию скрипта
    Expand-Archive -Path $zipFileName -DestinationPath $PSScriptRoot -Force
    Write-Log "Файлы успешно обновлены."
} catch {
    Write-Log "Ошибка при распаковке архива или замене файлов: $($_.Exception.Message)"
    # TODO: В случае серьезной ошибки здесь можно реализовать откат из резервной копии
    exit 1
} finally {
    # Очищаем временную директорию
    if (Test-Path -Path $TempDir) { Remove-Item -Path $TempDir -Recurse -Force | Out-Null }
}

# 5. Запуск службы
if (-not ([string]::IsNullOrEmpty($ServiceNameToStop))) {
    try {
        $service = Get-Service -Name $ServiceNameToStop -ErrorAction SilentlyContinue
        if ($service -and $service.Status -eq 'Stopped') {
            Write-Log "Запуск службы '$ServiceNameToStop'..."
            Start-Service -Name $ServiceNameToStop -ErrorAction Stop
            $service.WaitForStatus('Running', 60000) # Ожидаем до 60 секунд
            Write-Log "Служба '$ServiceNameToStop' успешно запущена."
        } elseif ($service -and $service.Status -eq 'Running') {
            Write-Log "Служба '$ServiceNameToStop' уже запущена."
        } else {
            Write-Log "Служба '$ServiceNameToStop' не найдена или ее статус неизвестен. Проверяйте вручную."
        }
    } catch {
        Write-Log "Ошибка при запуске службы '$ServiceNameToStop': $($_.Exception.Message)"
        exit 1
    }
}

Write-Log "Процесс обновления из URL завершен." 