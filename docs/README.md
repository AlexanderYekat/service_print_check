# 📚 Документация CloudPosBridge

Добро пожаловать в документацию системы CloudPosBridge! Здесь вы найдете всю необходимую информацию для установки, настройки и работы с системой.

## 📖 Руководства

### 🚀 Начало работы
- **[Инструкции по установке](installation_instructions.md)** - подробное руководство по установке и первоначальной настройке
- **[Быстрый старт](../README.md#быстрый-старт)** - краткая инструкция для быстрого запуска

### 🏗️ Архитектура
- **[Руководство по чистой архитектуре](clean_architecture_guide.md)** - принципы и подходы, используемые в проекте
- **[Идеальная структура](architecture/IDEAL_STRUCTURE.md)** - описание оптимальной организации кода

### 🌐 API
- **[API Reference](api/API_REFERENCE.md)** - полное описание всех API endpoints и их параметров

### 🚀 Развертывание
- **[Скрипты деплоя](../scripts/README.md)** - автоматизация процесса развертывания
- **[Настройка веб-сервера](#настройка-веб-сервера)** - конфигурация Apache/Nginx/IIS

## 📁 Структура документации

```
docs/
├── 📄 README.md                        # Этот файл
├── 📄 clean_architecture_guide.md      # Руководство по архитектуре
├── 📄 installation_instructions.md     # Инструкции по установке
├── 🖼️ install_choise_kkt.png          # Диаграмма выбора ККТ
├── 📁 api/
│   └── 📄 API_REFERENCE.md            # Справочник API
└── 📁 architecture/
    └── 📄 IDEAL_STRUCTURE.md          # Идеальная структура проекта
```

## 🔧 Настройка веб-сервера

### Apache

Создайте файл `.htaccess` в корне проекта:

```apache
RewriteEngine On

# Перенаправление на public/index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ public/index.php [QSA,L]

# Безопасность - запрет доступа к служебным папкам
RewriteRule ^(src|config|logs|tests|scripts)/ - [F,L]
```

### Nginx

Добавьте в конфигурацию сайта:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/CloudPosBridge/public;
    index index.php;

    # Основная логика роутинга
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP обработка
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Безопасность - запрет доступа к служебным папкам
    location ~ ^/(src|config|logs|tests|scripts)/ {
        deny all;
        return 403;
    }
}
```

### IIS (Windows)

Создайте файл `web.config` в корне проекта:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
        <rewrite>
            <rules>
                <rule name="CloudPosBridge" stopProcessing="true">
                    <match url="^(.*)$" />
                    <conditions>
                        <add input="{REQUEST_FILENAME}" matchType="IsFile" negate="true" />
                        <add input="{REQUEST_FILENAME}" matchType="IsDirectory" negate="true" />
                    </conditions>
                    <action type="Rewrite" url="public/index.php" />
                </rule>
            </rules>
        </rewrite>
        
        <!-- Безопасность -->
        <security>
            <requestFiltering>
                <hiddenSegments>
                    <add segment="src" />
                    <add segment="config" />
                    <add segment="logs" />
                    <add segment="tests" />
                    <add segment="scripts" />
                </hiddenSegments>
            </requestFiltering>
        </security>
    </system.webServer>
</configuration>
```

## 🛠️ Инструменты разработчика

### Тестирование
```bash
# Запуск всех тестов
php tests/run_all_tests.php

# Запуск конкретного теста
php tests/unit/VersionControllerTest.php
```

### Деплой
```bash
# Полный автоматический деплой
php scripts/deploy.php

# Быстрый деплой для разработки
./scripts/quick_deploy.sh
```

### Мониторинг
```bash
# Проверка состояния системы
curl http://localhost/api/health

# Просмотр логов
tail -f logs/app.log
```

## 📞 Получение помощи

Если у вас возникли вопросы:

1. **Сначала проверьте:**
   - [API Reference](api/API_REFERENCE.md) для работы с API
   - [Руководство по архитектуре](clean_architecture_guide.md) для понимания структуры
   - [Инструкции по установке](installation_instructions.md) для проблем с настройкой

2. **Диагностика:**
   - Запустите `php tests/run_all_tests.php` для проверки работоспособности
   - Проверьте `curl http://localhost/api/health` для состояния компонентов
   - Изучите логи в папке `logs/`

3. **Примеры использования:**
   - Посмотрите файлы в папке `examples/`
   - Изучите тесты в папке `tests/` для понимания использования

## 🗂️ Архивные документы

Старые документы по процессу рефакторинга перенесены в папку `docs_archive/` для справки.

---

💡 **Совет:** Начните с [инструкций по установке](installation_instructions.md), затем изучите [API Reference](api/API_REFERENCE.md) и [руководство по архитектуре](clean_architecture_guide.md).