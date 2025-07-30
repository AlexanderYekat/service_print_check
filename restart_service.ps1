# Скрипт для перезапуска службы CloudPosBridgePHP

Write-Host "Остановка службы CloudPosBridgeServicePHP..."
try {
    Stop-Service -Name CloudPosBridgeServicePHP -ErrorAction Stop
    Write-Host "Служба CloudPosBridgeServicePHP остановлена."
}
catch {
    Write-Warning "Не удалось остановить службу CloudPosBridgeServicePHP. Возможно, она уже остановлена или не существует. Продолжаем."
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

Write-Host "Перезапуск службы завершен." 
