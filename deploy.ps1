#$confirmation = Read-Host "Выполнить деплой? (y/n)"
if (1 -eq 1) {
    Write-Host n"Начинаем процесс деплоя..."
    
    # Остановка службы
    Write-Host "Останавливаем службу CloudPosBridge..."
    Stop-Service -Name "CloudPosBridge" -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 2
    
    # Путь установки
    $installPath = "C:\Program Files (x86)\CloudPosBridge"
    $programPath = "C:\Users\Enduro\Documents\fl\kwork\service_print_check"
    
    # Проверяем существование директории
    if (-not (Test-Path $installPath)) {
        Write-Host "Создаем директорию установки..."
        New-Item -ItemType Directory -Path $installPath -Force
    }
    
    # Удаляем старый файл
    if (Test-Path "$installPath\service_CloudPosBridge.exe") {
        Write-Host "Удаляем старый файл службы..."
        Remove-Item "$installPath\service_CloudPosBridge.exe" -Force
    }
    
    # Копируем новый файл
    Write-Host "Копируем новый файл службы..."
    Copy-Item "$programPath\service_print_check.exe" -Destination "$installPath\service_CloudPosBridge.exe" -Force
    
    # Запускаем службу
    Write-Host "Запускаем службу CloudPosBridge..."
    Start-Service -Name "CloudPosBridge"
    Start-Sleep -Seconds 2
    
    # Проверяем статус службы
    $service = Get-Service -Name "CloudPosBridge"
    if ($service.Status -eq "Running") {
        Write-Host "Деплой успешно завершен. Служба запущена."
    } else {
        Write-Host "Внимание: Служба не запустилась. Текущий статус: $($service.Status)"
    }
} else {
    Write-Host "Деплой отменен."
} 