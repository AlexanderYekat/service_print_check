chcp 65001
@echo off
REM Этот скрипт помогает автоматизировать процесс отправки изменений из async в prod-phprails.
REM Он предназначен для использования после того, как вы закончили разработку в async и готовы к релизу.

echo [Шаг 1/6] Переключение на ветку async...
git checkout async
IF %ERRORLEVEL% NEQ 0 (
    echo Ошибка: Не удалось переключиться на ветку async. Проверьте ваш репозиторий.
    goto :eof
)
echo [Шаг 2/6] Обновление async с удаленного репозитория...
git pull origin async
IF %ERRORLEVEL% NEQ 0 (
    echo Ошибка: Не удалось обновить async. Проверьте ваше сетевое соединение или права доступа.
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
echo [Шаг 5/6] Слияние async в prod-phprails...
git merge async
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
git checkout async
pause