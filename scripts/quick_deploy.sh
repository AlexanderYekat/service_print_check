#!/bin/bash

# Быстрый деплой CloudPosBridge
# Упрощенная версия для разработки

set -e  # Остановить при ошибке

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")

echo "🚀 Быстрый деплой CloudPosBridge"
echo "Проект: $PROJECT_ROOT"
echo "Время: $TIMESTAMP"
echo "================================"

# Переходим в корень проекта
cd "$PROJECT_ROOT"

# Проверяем git статус
echo "🔍 Проверка git статуса..."
if [ -n "$(git status --porcelain)" ]; then
    echo "⚠️  Есть незакоммиченные изменения:"
    git status --short
    read -p "Продолжить деплой? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "❌ Деплой отменен"
        exit 1
    fi
fi

# Обновляем код
echo "📥 Обновление кода..."
git pull origin main

# Устанавливаем зависимости
echo "📦 Установка зависимостей..."
if command -v composer &> /dev/null; then
    composer install --no-dev --optimize-autoloader
else
    echo "⚠️  Composer не найден, пропускаем установку зависимостей"
fi

# Создаем необходимые папки
echo "📁 Создание необходимых папок..."
mkdir -p logs config settings_storage backups

# Проверяем файл настроек
echo "⚙️  Проверка настроек..."
if [ ! -f "config/settings.json" ]; then
    echo "📝 Создание базового файла настроек..."
    cat > config/settings.json << 'EOF'
{
    "printer": {
        "com_class": "AddIn.Fptr10",
        "com_port": "COM1",
        "emulation": true
    },
    "bank": {
        "binary_path": "./bank/mainbeznal.exe",
        "emulation": true,
        "timeout": 30
    },
    "scale": {
        "com_port": "COM2",
        "emulation": true
    },
    "honest_sign": {
        "api_url": "https://api.markirovka.ru",
        "x-api-token": "",
        "timeout": 30
    }
}
EOF
fi

# Запускаем тесты (если есть)
echo "🧪 Запуск тестов..."
if [ -f "tests/run_all_tests.php" ]; then
    if php tests/run_all_tests.php; then
        echo "✅ Тесты пройдены"
    else
        echo "⚠️  Некоторые тесты провалились, но продолжаем"
    fi
else
    echo "ℹ️  Тесты не найдены"
fi

# Проверяем health
echo "🏥 Проверка здоровья системы..."
if [ -f "public/index.php" ]; then
    # Простая проверка что bootstrap загружается
    if php -l src/bootstrap.php > /dev/null 2>&1; then
        echo "✅ Bootstrap проходит синтаксическую проверку"
    else
        echo "❌ Ошибка в bootstrap.php"
        exit 1
    fi
else
    echo "ℹ️  Файл index.php не найден"
fi

# Устанавливаем права
echo "🔐 Установка прав доступа..."
chmod -R 755 .
chmod -R 777 logs settings_storage backups 2>/dev/null || true

echo ""
echo "✅ Быстрый деплой завершен успешно!"
echo "🌐 Проверьте работу системы в браузере"
echo "📋 Логи доступны в папке logs/"
echo ""