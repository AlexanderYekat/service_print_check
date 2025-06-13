$service = Get-Service -Name CloudPosBridgeServicePHP -ErrorAction SilentlyContinue
try {
	#echo "Остановка службы '$ServiceNameToStop'..."
	Stop-Service -Name CloudPosBridgeServicePHP -Force -ErrorAction Stop
	$service.WaitForStatus('Stopped', 30000) # Ожидаем до 60 секунд
	#echo "Служба '$ServiceNameToStop' успешно остановлена."
} catch {
   #Write-Log "Ошибка при остановке службы '$ServiceNameToStop': $($_.Exception.Message)"
   #echo "Ошибка при остановке службы '$ServiceNameToStop': $($_.Exception.Message)"
}
#echo "fddfdfdf"

# Создаем файл-маркер в той же директории, где находится скрипт
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$markerFile = Join-Path $scriptDir "service_stopped.txt"
"Service was stopped at $(Get-Date)" | Out-File $markerFile