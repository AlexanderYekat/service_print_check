# bank-return.ps1
# Этот скрипт выполняет операцию возврата денег через COM-объект SBRFSRV.Server
# и возвращает чек-слип в формате JSON.

param (
    [Parameter(Mandatory=$true)]
    [float]$amount
)

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
    }

    try {
        # Проверка, запущен ли сервис SBRFSRV (опционально, но полезно для диагностики)
        # Если сервис не запущен, COM-объект может быть недоступен
        $sbrfService = Get-WmiObject -Class Win32_Service -Filter "Name='SBRFSRV'" -ErrorAction SilentlyContinue
        if ($null -eq $sbrfService -or $sbrfService.State -ne 'Running') {
            $result.Message = "Сервис 'SBRFSRV' не запущен или COM-объект не зарегистрирован."
            return $result
        }

        # Создание COM-объекта
        $sber = New-Object -ComObject SBRFSRV.Server -ErrorAction Stop

        # Очистка предыдущих параметров
        $sber.Clear()

        # Установка параметра Amount
        $sber.SParam("Amount", $TransactionAmount)

        # Вызов NFun для операции возврата (код операции 4002)
        $nfunResult = $sber.NFun(4002)

        if ($nfunResult -eq 0) { # Успех
            # Получение параметра Cheque
            $cheque = $sber.GParamString("Cheque")

            # Очистка параметров (хорошая практика, хотя скрипт завершит работу)
            $sber.Clear()

            $result.Success = $true
            $result.Message = "Операция успешно выполнена."
            $result.Cheque = $cheque
        } else { # Ошибка
            # Попытка получить более конкретное описание ошибки от COM-объекта
            $errorCode = $sber.GParamString("ErrorCode")
            $errorDescription = $sber.GParamString("ErrorDescription")
            $result.Message = "Операция возврата не удалась. NFun вернул код: $nfunResult. Код ошибки: $errorCode, Описание: $errorDescription"
        }
    } catch [System.Runtime.InteropServices.COMException] {
        # Обработка специфических ошибок COM
        $result.Message = "Ошибка COM: $($_.Exception.Message). Убедитесь, что SBRFSRV.Server правильно зарегистрирован и запущен."
    } catch {
        # Обработка общих ошибок
        $result.Message = "Произошла непредвиденная ошибка: $($_.Exception.Message)"
    }

    return $result
}

# Вызов функции с переданной суммой
$operationResult = Invoke-BankReturn -TransactionAmount $amount

# Вывод результата в формате JSON для парсинга PHP
ConvertTo-Json $operationResult -Compress
