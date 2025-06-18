# bank-return.ps1
# Этот скрипт выполняет операцию возврата денег через COM-объект SBRFSRV.Server
# и возвращает чек-слип в формате JSON.

$baseDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
$tempDir = Join-Path $baseDir 'temp'
$amountFile = Join-Path $tempDir 'amount.txt'
$resultFile = Join-Path $tempDir 'result.json'

# Чтение суммы из файла
if (!(Test-Path $amountFile)) {
    $result = @{ Success = $false; Message = "Файл суммы не найден: $amountFile"; Cheque = "" }
    $result | ConvertTo-Json -Compress | Out-File -Encoding UTF8 $resultFile
    exit 1
}

$amountText = Get-Content $amountFile -Raw
$amount = $null
if (-not [float]::TryParse($amountText, [ref]$amount)) {
    $result = @{ Success = $false; Message = "Некорректная сумма в файле: $amountText"; Cheque = "" }
    $result | ConvertTo-Json -Compress | Out-File -Encoding UTF8 $resultFile
    exit 1
}

# Функция для инкапсуляции логики операции возврата
function Invoke-BankReturn {
    param (
        [Parameter(Mandatory=$true)]
        [float]$TransactionAmount
    )

    $result = @{
        Success = $false
        Message = "Начальное состояние"
        Cheque  = ""
        CodeReturn = 0
    }

    try {
        # Создание COM-объекта
        $sber = New-Object -ComObject SBRFSRV.Server -ErrorAction Stop
        $sber.Clear()
        $sber.SParam("Amount", $TransactionAmount)
        $nfunResult = $sber.NFun(4002)
        if ($nfunResult -eq 0) {
            $cheque = $sber.GParamString("Cheque")
            $sber.Clear()
            $result.Success = $true
            $result.Message = "Операция успешно выполнена."
            $result.Cheque = $cheque
        } else {
            $errorCode = $sber.GParamString("ErrorCode")
            $errorDescription = $sber.GParamString("ErrorDescription")
            $result.Message = "Операция возврата не удалась. NFun вернул код: $nfunResult. Код ошибки: $errorCode, Описание: $errorDescription"
            $result.CodeReturn = $nfunResult
        }
    } catch [System.Runtime.InteropServices.COMException] {
        $result.Message = "Ошибка COM: $($_.Exception.Message). Убедитесь, что SBRFSRV.Server правильно зарегистрирован и запущен."
    } catch {
        $result.Message = "Произошла непредвиденная ошибка: $($_.Exception.Message)"
    }
    return $result
}

# Вызов функции с переданной суммой
$operationResult = Invoke-BankReturn -TransactionAmount $amount

# Вывод результата в формате JSON для парсинга PHP
$operationResult | ConvertTo-Json -Compress | Out-File -Encoding UTF8 $resultFile
