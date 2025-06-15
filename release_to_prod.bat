chcp 65001
@echo off
REM Этот скрипт помогает автоматизировать процесс отправки изменений из phprails в prod-phprails.
REM Он предназначен для использования после того, как вы закончили разработку в phprails и готовы к релизу.

echo [Шаг 1/6] Переключение на ветку phprails...
git checkout phprails
IF %ERRORLEVEL% NEQ 0 (
    echo Ошибка: Не удалось переключиться на ветку phprails. Проверьте ваш репозиторий.
    goto :eof
)
echo [Шаг 2/6] Обновление phprails с удаленного репозитория...
git pull origin phprails
IF %ERRORLEVEL% NEQ 0 (
    echo Ошибка: Не удалось обновить phprails. Проверьте ваше сетевое соединение или права доступа.
    goto :eof
)
echo [Шаг 3/6] Переключение на ветку prod-phprails...
git checkout prod-phprails
IF %ERRORLEVEL% NEQ 0 (
    echo Ошибка: Не удалось переключиться на ветку prod-phprails. Проверьте, существует ли ветка.
    goto :eof
)
echo [Шаг 4/6] Обновление prod-phprails с удаленного репозитория...
git pull origin prod-phprails
IF %ERRORLEVEL% NEQ 0 (
    echo Ошибка: Не удалось обновить prod-phprails. Проверьте ваше сетевое соединение или права доступа.
    goto :eof
)
echo [Шаг 5/6] Слияние phprails в prod-phprails...
git merge phprails
IF %ERRORLEVEL% NEQ 0 (
    echo Ошибка: Конфликты слияния! Пожалуйста, разрешите их вручную перед повторным запуском скрипта.
    goto :eof
)
echo [Шаг 6/6] Отправка prod-phprails на GitHub (это запустит GitHub Action)...
git push origin prod-phprails
IF %ERRORLEVEL% NEQ 0 (
    echo Ошибка: Не удалось отправить prod-phprails. Проверьте ваше сетевое соединение или права доступа.
    goto :eof
)
echo.
echo Процесс завершен. Если не было ошибок, GitHub Action должен быть запущен.
git checkout phprails
pause