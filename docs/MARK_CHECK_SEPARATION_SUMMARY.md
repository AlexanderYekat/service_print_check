# Краткий обзор разделения проверки марки

## Что реализовано

✅ **Два независимых интерфейса:**
- `PermitMarkCheckGateway` - синхронная проверка разрешительного режима
- `EcrMarkCheckGateway` - асинхронная проверка ККТ

✅ **Два независимых Use Case:**
- `PermitMarkCheckUseCase` - синхронная проверка
- `EcrMarkCheckUseCase` - асинхронная проверка с taskId

✅ **Реализации:**
- `HttpPermitMarkCheckGateway` - HTTP API для разрешительного режима
- `QueueEcrMarkCheckGateway` - очередь для ККТ проверки
- `EcrMarkCheckQueue` - управление задачами и результатами
- `EcrMarkCheckWorker` - воркер для обработки очереди

✅ **API Endpoints:**
- `POST /api/permit-mark-check` - синхронная проверка
- `POST /api/ecr-mark-check/enqueue` - постановка в очередь
- `GET /api/ecr-mark-check/result/{taskId}` - получение результата

✅ **Инструменты:**
- Скрипт воркера: `scripts/run_ecr_worker.php`
- Пример использования: `examples/new_mark_check_example.php`

## Использование

### Разрешительный режим (синхронно)
```bash
curl -X POST http://localhost/api/permit-mark-check \
  -H "Content-Type: application/json" \
  -d '{"marking_code": "010463003759026521uHpB8gXVVdi"}'
```

### Проверка ККТ (асинхронно)
```bash
# 1. Ставим в очередь
curl -X POST http://localhost/api/ecr-mark-check/enqueue \
  -H "Content-Type: application/json" \
  -d '{"marking_code": "010463003759026521uHpB8gXVVdi"}'

# 2. Получаем taskId из ответа, например: "ecr_mark_60f7a123456789"

# 3. Проверяем результат
curl -X GET http://localhost/api/ecr-mark-check/result/ecr_mark_60f7a123456789
```

## Запуск воркера

```bash
php scripts/run_ecr_worker.php
```

## Структура результата

Все результаты в едином формате `OperationResult`:

```json
{
  "success": true,
  "data": {
    "user_status": {
      "ok": true,
      "text": "Сообщение для оператора"
    },
    "machine_data": {
      // Для permit: uuid, time, permitInfo
      // Для ecr: taskId, taskStatus, itemInfoCheckResult
    }
  }
}
```

## Принципы

1. **Разделение ответственности** - каждый режим имеет свой интерфейс
2. **Единый формат** - все через OperationResult
3. **Никаких printed_lines** - только бизнесовые данные
4. **Асинхронность для ККТ** - enqueue + getResult
5. **Синхронность для permit** - прямой вызов

## Миграция

Старую архитектуру (`ValidateMarkUseCase`) можно постепенно заменить на новую:

1. Контроллеры → новые endpoints
2. Use Cases → соответствующие новые классы
3. Настроить воркер для асинхронных задач
4. Удалить старые файлы

**Новая архитектура готова к использованию!** 🚀