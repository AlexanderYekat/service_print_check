# update_from_url.ps1
# Этот скрипт предназначен для запуска PHP-службой для обновления файлов приложения из ZIP-архива по URL.

Param(
    [string]$DownloadUrl,
    [string]$LogDirPath,
    [string]$ServiceNameToStop # Имя службы для остановки/запуска
)

$LogFile = Join-Path -Path $LogDirPath -ChildPath "update_from_url.log"
$TempDir = Join-Path -Path $PSScriptRoot -ChildPath "_temp_update"
$BackupBaseDir = Join-Path -Path $PSScriptRoot -ChildPath "backup"
$BackupDir = Join-Path -Path $BackupBaseDir -ChildPath "_backup_$(Get-Date -Format 'yyyyMMdd_HHmmss')"

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

echo "Начало процесса обновления из URL: $DownloadUrl"
Write-Log "Начало процесса обновления из URL: $DownloadUrl"
echo "Каталог для логов: $LogDirPath"
Write-Log "Каталог для логов: $LogDirPath"
Write-Log "Имя службы для остановки/запуска: $ServiceNameToStop"
echo "Имя службы для остановки/запуска: $ServiceNameToStop"

# 1. Загрузка ZIP-файла
try {
    Write-Log "Попытка загрузки архива из $DownloadUrl..."
    echo "Попытка загрузки архива из $DownloadUrl..."
    # Создаем временную директорию, если она не существует
    if (Test-Path -Path $TempDir) { Remove-Item -Path $TempDir -Recurse -Force | Out-Null }
    New-Item -Path $TempDir -ItemType Directory -Force | Out-Null

    $zipFileName = Join-Path -Path $TempDir -ChildPath "update.zip"
    (New-Object System.Net.WebClient).DownloadFile($DownloadUrl, $zipFileName)
    Write-Log "Архив успешно загружен в $zipFileName."
    echo "Архив успешно загружен в $zipFileName."
} catch {
    Write-Log "Ошибка при загрузке архива: $($_.Exception.Message)"
    echo "Ошибка при загрузке архива: $($_.Exception.Message)"
    exit 1
}

# 2. Остановка службы
if (-not ([string]::IsNullOrEmpty($ServiceNameToStop))) {
    try {
        $service = Get-Service -Name $ServiceNameToStop -ErrorAction SilentlyContinue
        if ($service -and $service.Status -eq 'Running') {
            Write-Log "Остановка службы '$ServiceNameToStop'..."
            echo "Остановка службы '$ServiceNameToStop'..."
            №Stop-Service -Name $ServiceNameToStop -Force -ErrorAction Stop
            $service.WaitForStatus('Stopped', 60000) # Ожидаем до 60 секунд
            Write-Log "Служба '$ServiceNameToStop' успешно остановлена."
            echo "Служба '$ServiceNameToStop' успешно остановлена."
        } elseif ($service -and $service.Status -eq 'Stopped') {
            Write-Log "Служба '$ServiceNameToStop' уже остановлена."
            echo "Служба '$ServiceNameToStop' уже остановлена."
        } else {
            Write-Log "Служба '$ServiceNameToStop' не найдена или ее статус неизвестен. Продолжаем без остановки."
            echo "Служба '$ServiceNameToStop' не найдена или ее статус неизвестен. Продолжаем без остановки."
        }
    } catch {
        Write-Log "Ошибка при остановке службы '$ServiceNameToStop': $($_.Exception.Message)"
        echo "Ошибка при остановке службы '$ServiceNameToStop': $($_.Exception.Message)"
    }
}

# 3. Создание резервной копии текущих файлов
try {
    # Убедитесь, что базовая директория для резервных копий существует
    if (-not (Test-Path -Path $BackupBaseDir -PathType Container)) {
        New-Item -Path $BackupBaseDir -ItemType Directory -Force | Out-Null
    }

    # Создаем директорию для текущей резервной копии
    New-Item -Path $BackupDir -ItemType Directory -Force | Out-Null

    Write-Log "Создание резервной копии текущих файлов в $BackupDir..."
    echo "Создание резервной копии текущих файлов в $BackupDir..."

    $backupZipFile = Join-Path -Path $BackupDir -ChildPath "application_backup.zip"

    # Определяем исходную директорию для архивации
    $sourceDirectory = $PSScriptRoot

    # Определяем список исключений
    $exclusions = @(
        "_temp_update", "_backup_*", "logs", "settings", "backup",
        ".github", "myapp_dist", ".gitattributes", ".gitignore",
        "CloudPosBridge_Installer.iss", "update_from_url.ps1",
        "atolservice.php", "restart_service.ps1",
        "*.tmp", "*.lock", "*.db", ".git"
    )

    # Получаем все элементы для архивации с учетом исключений
    $itemsToArchive = Get-ChildItem -Path $sourceDirectory -Exclude $exclusions | Select-Object -ExpandProperty FullName

    if ($itemsToArchive.Count -gt 0) {
        # Временно меняем директорию, чтобы избежать потенциальных блокировок на $PSScriptRoot
        $originalLocation = Get-Location
        $tempWorkingDir = Join-Path -Path (Get-Item Env:TEMP).Value -ChildPath "ps_backup_temp_$(Get-Date -Format 'yyyyMMdd_HHmmss')"
        New-Item -Path $tempWorkingDir -ItemType Directory -Force | Out-Null
        Set-Location -Path $tempWorkingDir

        try {
            # Передаем абсолютные пути в Compress-Archive, когда текущее местоположение изменено
            Compress-Archive -Path $itemsToArchive -DestinationPath $backupZipFile -Force
            Write-Log "Резервная копия успешно создана в $backupZipFile."
            echo "Резервная копия успешно создана в $backupZipFile."
        } finally {
            # Возвращаемся к исходному местоположению
            Set-Location -Path $originalLocation
            # Очищаем временную рабочую директорию
            if (Test-Path -Path $tempWorkingDir) { Remove-Item -Path $tempWorkingDir -Recurse -Force | Out-Null }
        }
    } else {
        Write-Log "Нечего архивировать для резервной копии. Пропускаем создание архива."
        echo "Нечего архивировать для резервной копии. Пропускаем создание архива."
    }
} catch {
    echo "Ошибка при создании резервной копии: $($_.Exception.Message)"
    Write-Log "Ошибка при создании резервной копии: $($_.Exception.Message)"
    # Продолжаем, так как это не критическая ошибка для самого обновления, но логируем
}

# 4. Распаковка и замена файлов
try {
    echo "Распаковка архива и обновление файлов..."
    Write-Log "Распаковка архива и обновление файлов..."
    # Удаляем содержимое текущей директории, кроме временных папок, логов и настроек
    Get-ChildItem -Path $PSScriptRoot -Exclude "_temp_update", "backup", "logs", ".github", "myapp_dist", ".gitattributes", ".gitignore", "CloudPosBridge_Installer.iss", "settings" | ForEach-Object { Remove-Item -Path $_.FullName -Recurse -Force | Out-Null }
    
    # Извлекаем содержимое архива непосредственно в текущую директорию скрипта
    Expand-Archive -Path $zipFileName -DestinationPath $PSScriptRoot -Force
    Write-Log "Файлы успешно обновлены."
    echo "Файлы успешно обновлены."
} catch {
    Write-Log "Ошибка при распаковке архива или замене файлов: $($_.Exception.Message)"
    echo "Ошибка при распаковке архива или замене файлов: $($_.Exception.Message)"
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
            echo "Запуск службы '$ServiceNameToStop'..."
            Start-Service -Name $ServiceNameToStop -ErrorAction Stop
            $service.WaitForStatus('Running', 60000) # Ожидаем до 60 секунд
            Write-Log "Служба '$ServiceNameToStop' успешно запущена."
            echo "Служба '$ServiceNameToStop' успешно запущена."
        } elseif ($service -and $service.Status -eq 'Running') {
            Write-Log "Служба '$ServiceNameToStop' уже запущена."
            echo "Служба '$ServiceNameToStop' уже запущена."
        } else {
            Write-Log "Служба '$ServiceNameToStop' не найдена или ее статус неизвестен. Проверяйте вручную."
            echo "Служба '$ServiceNameToStop' не найдена или ее статус неизвестен. Проверяйте вручную."
        }
    } catch {
        Write-Log "Ошибка при запуске службы '$ServiceNameToStop': $($_.Exception.Message)"
        echo "Ошибка при запуске службы '$ServiceNameToStop': $($_.Exception.Message)"
    }
}

Write-Log "Процесс обновления из URL завершен." 
echo "Процесс обновления из URL завершен." 