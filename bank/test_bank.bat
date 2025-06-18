chcp 65001
@echo off
REM Тестовый запуск bank-return.ps1
REM Использование: test_bank_return.bat 123.45

set AMOUNT=%1

if "%AMOUNT%"=="" (
    echo Укажите сумму возврата как аргумент, например: test_bank_return.bat 123.45
    exit /b 1
)

powershell -ExecutionPolicy Bypass -File ./bank-return.ps1 -amount %AMOUNT%
pause
