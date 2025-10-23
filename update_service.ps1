# Скрипт для обновления службы CloudPosBridgePHP

# Указываем путь к корневой папке вашего проекта
$ProjectSourcePath = (Get-Item -Path $PSScriptRoot).FullName

# Указываем путь к папке установки службы
$ServiceInstallPath = "C:\CloudPosBridgePHP\app"

Write-Host "Остановка службы CloudPosBridgeServicePHP..."
try {
    Stop-Service -Name CloudPosBridgeServicePHP -ErrorAction Stop
    Write-Host "Служба CloudPosBridgeServicePHP остановлена."
}
catch {
    Write-Warning "Не удалось остановить службу CloudPosBridgeServicePHP. Возможно, она уже остановлена или не существует. Продолжаем."
}

Write-Host "Копирование файлов проекта в [$ServiceInstallPath]..."
try {
    # Создаем целевую папку, если она не существует
    if (-not (Test-Path -Path $ServiceInstallPath)) {
        New-Item -Path $ServiceInstallPath -ItemType Directory -Force | Out-Null
        Write-Host "Создана целевая папка: $ServiceInstallPath"
    }

    # Копируем PHP файлы из корневой папки проекта
    Get-ChildItem -Path $ProjectSourcePath -Filter "*.php" -File | ForEach-Object {
        Copy-Item -Path $_.FullName -Destination $ServiceInstallPath -Force
    }
    Write-Host "PHP файлы скопированы."
    Get-ChildItem -Path $ProjectSourcePath -Filter "*.ps1" -File | ForEach-Object {
        Copy-Item -Path $_.FullName -Destination $ServiceInstallPath -Force
    }
    Write-Host "Файлы скриптов PowerSHell скопированы."

    # Копируем README.md
    Copy-Item -Path "$ProjectSourcePath\README.md" -Destination $ServiceInstallPath -Force -ErrorAction SilentlyContinue
    Write-Host "README.md скопирован (если существует)."

    # Копируем содержимое папок с сохранением иерархии
    $foldersToCopy = @("templates", "settings_storage", "samples", "resource", "bank")
    foreach ($folder in $foldersToCopy) {
        $source = Join-Path $ProjectSourcePath $folder
        $destination = $ServiceInstallPath
        if (Test-Path -Path $source) {
            Write-Host "Копирование папки [$folder]..."
            Copy-Item -Path $source -Destination $destination -Recurse -Force
            Write-Host "Папка [$folder] скопирована."
        } else {
            Write-Warning "Папка [$folder] не найдена в проекте: $source"
        }
    }
    Write-Host "Все необходимые файлы скопированы."
}
catch {
    Write-Error "Ошибка при копировании файлов: $_"
    exit 1
}

Write-Host "Запуск службы CloudPosBridgeServicePHP..."
try {
    Start-Service -Name CloudPosBridgeServicePHP -ErrorAction Stop
    Write-Host "Служба CloudPosBridgeServicePHP запущена."
}
catch {
    Write-Error "Не удалось запустить службу CloudPosBridgeServicePHP: $_"
    exit 1
}

Write-Host "Процесс обновления завершен." 