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
echo [Шаг 5/7] Слияние async в prod-phprails (fast-forward)...
git merge --ff-only async
IF %ERRORLEVEL% NEQ 0 (
    echo Fast-forward невозможен. Пробую авторазрешение конфликтов в пользу async...
    git merge -X theirs --no-edit async
    IF %ERRORLEVEL% NEQ 0 (
        echo Ошибка: Автоматическое слияние не удалось. Ниже список файлов с конфликтами:
        git --no-pager diff --name-only --diff-filter=U
        echo Подсказка: откройте эти файлы и устраните маркеры конфликтов.
        goto :eof
    )
)

echo [Шаг 6/7] Проверка на маркеры конфликтов...
git ls-files -u | findstr /r "." >nul
IF %ERRORLEVEL% EQU 0 (
    echo Ошибка: В индексе остались неслитые файлы. Список:
    git --no-pager diff --name-only --diff-filter=U
    goto :eof
)
echo Конфликтов не обнаружено.

echo [Шаг 7/7] Отправка prod-phprails на GitHub (это запустит GitHub Action)...
git push origin prod-phprails
IF %ERRORLEVEL% NEQ 0 (
    echo Ошибка: Не удалось отправить prod-phprails. Проверьте ваше сетевое соединение или права доступа.
    goto :eof
)
git checkout async
echo.
echo Процесс завершен. Если не было ошибок, GitHub Action должен быть запущен.
git checkout async
pause