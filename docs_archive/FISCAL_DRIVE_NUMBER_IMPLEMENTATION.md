# Реализация передачи и кэширования fiscalDriveNumber

## Обзор

Полная реализация ТЗ по передаче и кэшированию заводского номера фискального накопителя (fiscalDriveNumber) для проверки маркировки в разрешительном режиме.

## ✅ Выполненные задачи

### 1. Доменный слой
- ✅ **Модель MarkingCode НЕ содержит поле fiscalDriveNumber** (согласно требованию)
- ✅ **Передача fiscalDriveNumber через контекст** в PermitMarkCheckUseCase

### 2. Infrastructure
- ✅ **Расширен SettingsStorageInterface** методами:
  - `getFiscalDriveNumber(): ?string`
  - `setFiscalDriveNumber(string $fiscalDriveNumber): void`
  - `get(string $key, $default = null)`
  - `set(string $key, $value): void`

- ✅ **Создан JsonFileSettingsStorage** с полной реализацией кэширования
- ✅ **Добавлен метод в SerialKktAdapter**:
  - `readFiscalDriveNumberFromDevice(): string`

### 3. API и UseCase
- ✅ **Модифицирован PermitMarkCheckUseCase** для принятия контекста
- ✅ **Обновлен PermitMarkCheckController** для передачи fiscalDriveNumber
- ✅ **Добавлена настройка** `includeFiscalDriveNumberInMarkCheck: true|false`

### 4. Бизнес-логика
- ✅ **Кэширование**: сначала проверка в settings_storage, потом чтение с ККТ
- ✅ **Обработка ошибок**: HTTP 503 при недоступности ККТ
- ✅ **Логирование**: все операции логируются

### 5. UI/Конфиг
- ✅ **Консольная утилита** для управления fiscalDriveNumber

## 📁 Измененные файлы

### Интерфейсы
```
src/interface/SettingsStorageInterface.php - расширен новыми методами
src/interface/PermitMarkCheckGateway.php - добавлен параметр context
```

### Infrastructure
```
src/infrastructure/settings_storage/JsonFileSettingsStorage.php - НОВЫЙ ФАЙЛ
src/infrastructure/printer/SerialKktAdapter.php - добавлен метод readFiscalDriveNumberFromDevice()
src/infrastructure/honest_sign/HttpPermitMarkCheckGateway.php - поддержка fiscalDriveNumber в запросах
```

### Domain/UseCase
```
src/domain/service/PermitMarkCheckUseCase.php - поддержка контекста
```

### API
```
src/api/PermitMarkCheckController.php - логика получения и передачи fiscalDriveNumber
```

### CLI
```
src/cli/FiscalDriveNumberManager.php - НОВЫЙ ФАЙЛ - консольная утилита
```

### Конфигурация
```
config/settings.json - добавлена настройка includeFiscalDriveNumberInMarkCheck
```

## 🔧 Примеры использования

### API запрос с fiscalDriveNumber
```bash
POST /api/permit-mark-check
{
    "marking_code": "01234567890123456789",
    "inn": "7714407000",
    "gtin": "04607090012345"
}
```

При включенной настройке `includeFiscalDriveNumberInMarkCheck: true` автоматически добавляется fiscalDriveNumber в запрос к внешнему API.

### Консольное управление
```bash
# Показать текущий номер ФН
php src/cli/FiscalDriveNumberManager.php show

# Обновить номер ФН с ККТ
php src/cli/FiscalDriveNumberManager.php update

# Сбросить кэш
php src/cli/FiscalDriveNumberManager.php reset

# Установить вручную
php src/cli/FiscalDriveNumberManager.php set 9999078900000961
```

## 🔄 Бизнес-поток

1. **При первом обращении к /api/permit-mark-check:**
   - Проверяем настройку `includeFiscalDriveNumberInMarkCheck`
   - Если включена - проверяем кэш в settings_storage
   - Если нет в кэше - читаем с ККТ и сохраняем
   - Добавляем fiscalDriveNumber в контекст use-case

2. **При последующих обращениях:**
   - Используем закэшированное значение

3. **При замене ФН:**
   - Используем консольную команду для сброса/обновления

## 🚫 Сценарии ошибок

- **Недоступность ККТ**: HTTP 503 + понятное сообщение
- **Настройка выключена**: fiscalDriveNumber не передается
- **Ошибка чтения с ККТ**: логируется, возвращается null

## ⚙️ Конфигурация

В `config/settings.json`:

```json
{
  "honest_sign": {
    "includeFiscalDriveNumberInMarkCheck": true
  },
  "printer": {
    "com_class": "AddIn.Fptr10",
    "com_port": "COM1",
    "emulation": false
  }
}
```

## 📝 Логирование

Все операции с fiscalDriveNumber логируются:
- Получение из кэша
- Чтение с ККТ устройства
- Сохранение в кэш
- Ошибки получения
- Консольные операции

## 🧪 Тестирование

Протестированы сценарии:
- ✅ Передача номера ФН в API запросе
- ✅ Кэширование и отсутствие дублирования запросов к ККТ
- ✅ Обработка ошибок ККТ
- ✅ Работа в режиме эмуляции
- ✅ Консольные команды управления

## 📋 Критерии готовности - ВЫПОЛНЕНО

- ✅ Проверка маркировки в разрешительном режиме корректно включает fiscalDriveNumber
- ✅ В доменной модели маркировки нет поля fiscalDriveNumber
- ✅ Реализовано кэширование и сброс номера ФН через settings_storage
- ✅ Логируется получение и изменение fiscalDriveNumber
- ✅ Все сценарии протестированы

## 🔄 Совместимость

Все изменения обратно совместимы:
- Старые вызовы API продолжают работать
- Новый параметр context опционален
- Настройка включения fiscalDriveNumber управляема

---

**Статус: РЕАЛИЗОВАНО ПОЛНОСТЬЮ** ✅

Все требования ТЗ выполнены. Система готова к эксплуатации.