# Скрипты деплоя CloudPosBridge

Эта папка содержит скрипты для автоматического развертывания и обновления системы CloudPosBridge.

## Доступные скрипты

### 1. `deploy.php` - Полный автоматический деплой

Комплексный PHP скрипт, выполняющий полный цикл деплоя:

- ✅ Проверка предварительных условий
- 💾 Создание резервной копии
- 📥 Обновление кода из git
- 📦 Установка зависимостей через composer
- ⚙️ Миграция настроек
- 🧪 Запуск тестов
- 🔄 Перезапуск сервисов
- 🏥 Проверка здоровья системы
- 🔄 Автоматический откат при ошибках

**Использование:**
```bash
# Linux/macOS
php scripts/deploy.php

# Windows
php scripts\deploy.php
```

### 2. `quick_deploy.sh` - Быстрый деплой (Linux/macOS)

Упрощенный bash скрипт для быстрого обновления в процессе разработки.

**Использование:**
```bash
chmod +x scripts/quick_deploy.sh
./scripts/quick_deploy.sh
```

### 3. `quick_deploy.ps1` - Быстрый деплой (Windows)

PowerShell версия быстрого деплоя для Windows.

**Использование:**
```powershell
# Обычный режим
.\scripts\quick_deploy.ps1

# Принудительный режим (игнорировать незакоммиченные изменения)
.\scripts\quick_deploy.ps1 -Force
```

## Требования

### Минимальные требования

- **PHP 7.4+** с модулями: json, curl, mysqli
- **Git** для управления версиями
- **Права на запись** в директорию проекта

### Для полного деплоя (deploy.php)

- **Composer** для управления зависимостями
- **Веб-сервер** (Apache/Nginx/IIS)
- **Доступ к интернету** для загрузки зависимостей

## Структура папок после деплоя

```
CloudPosBridge/
├── config/           # Конфигурационные файлы
│   └── settings.json
├── logs/            # Логи приложения и деплоя
├── settings_storage/ # Кэш и временные данные
├── backups/         # Резервные копии (создаются при деплое)
├── src/             # Исходный код
├── tests/           # Тесты
└── scripts/         # Скрипты деплоя
```

## Настройка окружения

### Первоначальная настройка

1. **Клонирование репозитория:**
```bash
git clone <repository-url> CloudPosBridge
cd CloudPosBridge
```

2. **Создание конфигурации:**
```bash
cp config/settings.example.json config/settings.json
# Отредактируйте настройки под ваше окружение
```

3. **Установка зависимостей:**
```bash
composer install
```

4. **Настройка прав доступа (Linux/macOS):**
```bash
chmod -R 755 .
chmod -R 777 logs settings_storage backups
```

### Конфигурация веб-сервера

#### Apache (.htaccess)
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ public/index.php [QSA,L]
```

#### Nginx
```nginx
location / {
    try_files $uri $uri/ /public/index.php?$query_string;
}
```

## Процесс деплоя

### Автоматический деплой (рекомендуется)

```bash
# 1. Убедитесь что все изменения закоммичены
git add .
git commit -m "Описание изменений"
git push origin main

# 2. Запустите автоматический деплой
php scripts/deploy.php
```

### Ручной деплой

```bash
# 1. Обновление кода
git pull origin main

# 2. Установка зависимостей
composer install --no-dev --optimize-autoloader

# 3. Создание необходимых папок
mkdir -p logs config settings_storage backups

# 4. Проверка настроек
php -l src/bootstrap.php

# 5. Запуск тестов
php tests/run_all_tests.php

# 6. Проверка здоровья
curl http://localhost/api/health
```

## Мониторинг и логирование

### Логи деплоя
- `logs/deploy.log` - логи процесса деплоя
- `logs/app.log` - основные логи приложения

### Проверка состояния системы
```bash
# Через API
curl http://localhost/api/health

# Через браузер
http://localhost/api/health
```

### Основные команды диагностики
```bash
# Проверка синтаксиса PHP
php -l src/bootstrap.php

# Запуск тестов
php tests/run_all_tests.php

# Проверка логов
tail -f logs/app.log
```

## Откат версии

### Автоматический откат
При использовании `deploy.php` откат происходит автоматически при ошибках.

### Ручной откат
```bash
# Откат к предыдущему коммиту
git reset --hard HEAD~1

# Восстановление из бэкапа
cp -r backups/backup_YYYY-MM-DD_HH-MM-SS/* .
```

## Troubleshooting

### Частые проблемы

**Проблема: Composer не найден**
```bash
# Установка composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

**Проблема: Права доступа**
```bash
# Установка правильных прав
sudo chown -R www-data:www-data .
chmod -R 755 .
chmod -R 777 logs settings_storage backups
```

**Проблема: Git ошибки**
```bash
# Сброс незакоммиченных изменений
git stash
# или
git reset --hard HEAD
```

### Контакты для поддержки

При возникновении проблем с деплоем:
1. Проверьте логи в `logs/deploy.log`
2. Убедитесь что все требования выполнены
3. Попробуйте ручной деплой для диагностики
4. Обратитесь к документации в `docs/`

---

**Примечание:** Всегда тестируйте деплой на тестовом окружении перед применением на продакшене!