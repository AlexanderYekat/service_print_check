# Асинхронная проверка маркировки - API

## Описание

Асинхронный API для проверки кодов маркировки позволяет не блокировать клиентское приложение на время проверки. 

### Преимущества:
- ✅ Клиент получает ответ немедленно (не ждет завершения проверки на ККТ)
- ✅ Можно запустить несколько проверок параллельно
- ✅ Возможность проверить результат позже
- ✅ Не блокирует интерфейс 1С при длительной проверке

## Endpoints

### 1. Запуск проверки марки

**POST** `/api/check-marking-code-async`

Запускает проверку марки и немедленно возвращает ID задачи.

#### Запрос:

```json
{
  "markingCode": "0104607008480429215KpOL.jHnx6Xm93kl/E",
  "sellOrReturn": "sell",
  "itemEstimatedStatus": ""
}
```

**Параметры:**
- `markingCode` (обязательный) - код маркировки
- `sellOrReturn` (опционально, по умолчанию "sell") - тип операции: "sell", "return", "buyReturn"
- `itemEstimatedStatus` (опционально) - статус товара

#### Ответ (успешный):

```json
{
  "success": true,
  "message": "Задача принята в обработку",
  "data": {
    "taskId": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

#### Ответ (ошибка):

```json
{
  "success": false,
  "message": "Код маркировки не может быть пустым"
}
```

---

### 2. Получение результата проверки

**GET** `/api/check-marking-result/{taskId}`

Получает статус и результат проверки марки по ID задачи.

#### Запрос:

```
GET /api/check-marking-result/550e8400-e29b-41d4-a716-446655440000
```

#### Ответ (задача выполняется):

```json
{
  "success": true,
  "message": "Задача выполняется",
  "data": {
    "status": "processing",
    "taskId": "550e8400-e29b-41d4-a716-446655440000",
    "createdAt": "2025-10-09 14:30:15"
  }
}
```

#### Ответ (задача завершена):

```json
{
  "success": true,
  "message": "Проверка завершена",
  "data": {
    "status": "completed",
    "taskId": "550e8400-e29b-41d4-a716-446655440000",
    "result": {
      "success": true,
      "response": {
        "itemInfoCheckResult": {
          "imcCheckFlag": true,
          "imcStatusInfo": 1,
          "imcEstimatedStatusInfo": 0
        }
      },
      "error": ""
    },
    "completedAt": "2025-10-09 14:30:18"
  }
}
```

#### Ответ (задача завершена с ошибкой):

```json
{
  "success": true,
  "message": "Ошибка при проверке",
  "data": {
    "status": "error",
    "taskId": "550e8400-e29b-41d4-a716-446655440000",
    "error": "Смена не открыта - поэтому не можем проверить марки на ККТ",
    "errorAt": "2025-10-09 14:30:16"
  }
}
```

## Статусы задачи

| Статус | Описание |
|--------|----------|
| `pending` | Задача создана, ожидает обработки |
| `processing` | Задача выполняется |
| `completed` | Задача завершена успешно |
| `error` | Задача завершена с ошибкой |

## Примеры использования

### JavaScript (browser/node)

```javascript
async function checkMarkAsync(markingCode) {
  // 1. Запускаем проверку
  const startResponse = await fetch('http://localhost:8000/api/check-marking-code-async', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ markingCode })
  });
  
  const startData = await startResponse.json();
  if (!startData.success) {
    console.error('Ошибка запуска:', startData.message);
    return;
  }
  
  const taskId = startData.data.taskId;
  console.log('Задача запущена:', taskId);
  
  // 2. Опрашиваем статус
  while (true) {
    await new Promise(resolve => setTimeout(resolve, 1000)); // Ждем 1 секунду
    
    const resultResponse = await fetch(`http://localhost:8000/api/check-marking-result/${taskId}`);
    const resultData = await resultResponse.json();
    
    if (resultData.data.status === 'completed') {
      console.log('Проверка завершена:', resultData.data.result);
      break;
    } else if (resultData.data.status === 'error') {
      console.error('Ошибка проверки:', resultData.data.error);
      break;
    } else {
      console.log('Проверка в процессе...');
    }
  }
}
```

### 1С (см. файл tests/1ctest.bsl)

```bsl
// Запуск проверки
РезультатЗапуска = НачатьПроверкуМарки("0104607008480429215KpOL.jHnx6Xm93kl/E", "sell");

Если РезультатЗапуска.success Тогда
    ИдентификаторЗадачи = РезультатЗапуска.taskId;
    
    // Опрос статуса
    Пока Истина Цикл
        Ждать(1);
        РезультатПроверки = ПолучитьРезультатПроверкиМарки(ИдентификаторЗадачи);
        
        Если РезультатПроверки.status = "completed" Тогда
            // Обработка результата
            Прервать;
        ИначеЕсли РезультатПроверки.status = "error" Тогда
            // Обработка ошибки
            Прервать;
        КонецЕсли;
    КонецЦикла;
КонецЕсли;
```

### Python

```python
import requests
import time

def check_mark_async(marking_code):
    # 1. Запускаем проверку
    response = requests.post('http://localhost:8000/api/check-marking-code-async', json={
        'markingCode': marking_code
    })
    data = response.json()
    
    if not data['success']:
        print(f"Ошибка запуска: {data['message']}")
        return
    
    task_id = data['data']['taskId']
    print(f"Задача запущена: {task_id}")
    
    # 2. Опрашиваем статус
    while True:
        time.sleep(1)  # Ждем 1 секунду
        
        response = requests.get(f'http://localhost:8000/api/check-marking-result/{task_id}')
        data = response.json()
        
        status = data['data']['status']
        if status == 'completed':
            print(f"Проверка завершена: {data['data']['result']}")
            break
        elif status == 'error':
            print(f"Ошибка проверки: {data['data']['error']}")
            break
        else:
            print("Проверка в процессе...")
```

## Очистка задач

Сервер автоматически удаляет старые задачи (старше 24 часов) при каждом запуске. Это предотвращает накопление файлов задач.

## Хранение задач

Задачи хранятся в директории `tasks/` в формате JSON. Каждая задача сохраняется в отдельном файле с именем `{taskId}.json`.

Пример структуры файла задачи:

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "type": "check_marking_code",
  "status": "completed",
  "params": {
    "markingCode": "0104607008480429215KpOL.jHnx6Xm93kl/E",
    "sellOrReturn": "sell",
    "itemEstimatedStatus": ""
  },
  "result": {
    "success": true,
    "response": { ... }
  },
  "error": null,
  "createdAt": "2025-10-09 14:30:15",
  "updatedAt": "2025-10-09 14:30:18"
}
```

## Рекомендации

1. **Интервал опроса**: рекомендуется опрашивать статус каждые 1-2 секунды
2. **Таймаут**: установите максимальное время ожидания (например, 60 секунд)
3. **Обработка ошибок**: всегда проверяйте поле `success` в ответе
4. **Сохранение ID**: в реальных приложениях сохраняйте `taskId` в базу данных для возможности проверить результат позже

## Сравнение с синхронным API

| Характеристика | Синхронный API | Асинхронный API |
|---------------|----------------|-----------------|
| Endpoint | `/api/check-marking-code` | `/api/check-marking-code-async` |
| Время ответа | 3-10 секунд | < 100 мс |
| Блокировка клиента | Да | Нет |
| Требует опроса | Нет | Да |
| Параллельные проверки | Ограничено | Неограничено |

## Troubleshooting

### Проблема: Задача зависла в статусе "processing"

**Решение**: Перезапустите сервис. Задача будет удалена при очистке старых задач.

### Проблема: Получаю 404 при запросе результата

**Решение**: Проверьте правильность `taskId`. Возможно, задача была удалена из-за устаревания (> 24 часов).

### Проблема: Фоновая обработка не работает

**Решение**: Убедитесь, что PHP настроен для работы с `fastcgi_finish_request()` или используется альтернативный метод завершения ответа.

