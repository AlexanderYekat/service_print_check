$service = Get-Service -Name CloudPosBridgeServicePHP -ErrorAction SilentlyContinue
try {
	echo "Остановка службы '$ServiceNameToStop'..."
	Stop-Service -Name CloudPosBridgeServicePHP -Force -ErrorAction Stop
	$service.WaitForStatus('Stopped', 30000) # Ожидаем до 60 секунд
	echo "Служба '$ServiceNameToStop' успешно остановлена."
} catch {
   #Write-Log "Ошибка при остановке службы '$ServiceNameToStop': $($_.Exception.Message)"
   echo "Ошибка при остановке службы '$ServiceNameToStop': $($_.Exception.Message)"
}
echo "fddfdfdf"