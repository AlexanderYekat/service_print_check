# bank-return.ps1
# Универсальный скрипт для операций с банковским терминалом через COM-объект SBRFSRV.Server

$baseDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
$tempDir = Join-Path $baseDir 'temp'
$amountFile = Join-Path $tempDir 'amount.txt'
$operationFile = Join-Path $tempDir 'operation.txt'
$resultFile = Join-Path $tempDir 'result.json'

# Чтение типа операции
if (!(Test-Path $operationFile)) {
    $result = @{ Success = $false; Message = "Файл типа операции не найден: $operationFile"; Cheque = "" }
    $result | ConvertTo-Json -Compress | Out-File -Encoding UTF8 $resultFile
    exit 1
}
$operation = Get-Content $operationFile -Raw
$operation = $operation.Trim().ToLower()

# Определяем, нужна ли сумма
$needAmount = $operation -in @('pay','return','cancel')
$amount = $null
if ($needAmount) {
    if (!(Test-Path $amountFile)) {
        $result = @{ Success = $false; Message = "Файл суммы не найден: $amountFile"; Cheque = "" }
        $result | ConvertTo-Json -Compress | Out-File -Encoding UTF8 $resultFile
        exit 1
    }
    $amountText = Get-Content $amountFile -Raw
    if (-not [float]::TryParse($amountText, [ref]$amount)) {
        $result = @{ Success = $false; Message = "Некорректная сумма в файле: $amountText"; Cheque = "" }
        $result | ConvertTo-Json -Compress | Out-File -Encoding UTF8 $resultFile
        exit 1
    }
}

function Invoke-BankOperation {
    param (
        [string]$Operation,
        [float]$TransactionAmount = 0
    )
    $result = @{
        Success = $false
        Message = "Начальное состояние"
        Cheque  = ""
        CodeReturn = 0
    }
    try {
        $sber = New-Object -ComObject SBRFSRV.Server -ErrorAction Stop
        $sber.Clear()
        switch ($Operation) {
            'pay' {
                $sber.SParam("Amount", $TransactionAmount)
                $nfunResult = $sber.NFun(4000)
            }
            'return' {
                $sber.SParam("Amount", $TransactionAmount)
                $nfunResult = $sber.NFun(4002)
            }
            'cancel' {
                $sber.SParam("Amount", $TransactionAmount)
                $nfunResult = $sber.NFun(6004)
            }
            'close_shift' {
                $nfunResult = $sber.NFun(6000)
            }
            default {
                $result.Message = "Неизвестная операция: $Operation"
                return $result
            }
        }
        if ($nfunResult -eq 0) {
            $cheque = $sber.GParamString("Cheque")
            $sber.Clear()
            $result.Success = $true
            $result.Message = "Операция успешно выполнена."
            $result.Cheque = $cheque
        } else {
            $errorCode = $sber.GParamString("ErrorCode")
            $errorDescription = $sber.GParamString("ErrorDescription")
            $result.Message = "Операция не удалась. NFun вернул код: $nfunResult. Код ошибки: $errorCode, Описание: $errorDescription"
            $result.CodeReturn = $nfunResult
        }
    } catch [System.Runtime.InteropServices.COMException] {
        $result.Message = "Ошибка COM: $($_.Exception.Message). Убедитесь, что SBRFSRV.Server правильно зарегистрирован и запущен."
    } catch {
        $result.Message = "Произошла непредвиденная ошибка: $($_.Exception.Message)"
    }
    return $result
}

# Вызов функции с нужными параметрами
if ($needAmount) {
    $operationResult = Invoke-BankOperation -Operation $operation -TransactionAmount $amount
} else {
    $operationResult = Invoke-BankOperation -Operation $operation
}

$operationResult | ConvertTo-Json -Compress | Out-File -Encoding UTF8 $resultFile
