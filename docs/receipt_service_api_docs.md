# API документация сервиса печати чеков

## Обзор

Сервис печати чеков предоставляет REST API для работы с кассовым оборудованием и банковскими терминалами. API работает по HTTP протоколу и использует JSON для обмена данными.

**Базовый URL:** `http://localhost:8000`

**Страница настроек:** `http://localhost:8000` - веб-интерфейс для настройки и конфигурации сервиса

## Общие принципы

### Формат запросов
- Все запросы используют HTTP методы POST (кроме получения версии)
- Content-Type: `application/json`
- Тело запроса должно содержать валидный JSON

### Формат ответов
Все ответы возвращаются в формате JSON со следующей структурой:

```json
{
  "success": boolean,
  "message": "string (необязательно)",
  "data": {
    "success": boolean,
    "response": "any"
  }
}
```

### Обработка ошибок
- При успешной отправке команды на устройство: `success: true`
- При ошибке отправки команды: `success: false`, `message` содержит описание ошибки
- Дополнительно в `data.success` указывается результат выполнения операции на устройстве

---

## Эндпоинты API

### 1. Печать чека
**POST** `/api/print-check`

Печатает чек продажи или возврата на кассовом аппарате.

#### Параметры запроса:
```json
{
  "tableData": [
    {
      "name": "string",      // Наименование товара
      "quantity": "string",  // Количество
      "price": "string"      // Цена за единицу
    }
  ],
  "cashier": "string",       // ФИО кассира
  "payments": [
    {
      "type": "cash|electronically",  // Тип оплаты
      "amount": number                // Сумма
    }
  ],
  "type": "sell|return"      // Тип операции
}
```

#### Пример запроса:
```json
{
  "tableData": [
    {
      "name": "Товар 1",
      "quantity": "2",
      "price": "100.00"
    },
    {
      "name": "Товар 2",
      "quantity": "1",
      "price": "200.00"
    }
  ],
  "cashier": "Иван Иванов",
  "payments": [
    {
      "type": "cash",
      "amount": 300.00
    },
    {
      "type": "electronically",
      "amount": 100.00
    }
  ],
  "type": "sell"
}
```

---

### 2. Закрытие смены
**POST** `/api/close-shift`

Закрывает кассовую смену и печатает Z-отчет.

#### Параметры запроса:
```json
{
  "cashier": "string"  // ФИО кассира
}
```

#### Пример запроса:
```json
{
  "cashier": "Иван Иванов"
}
```

---

### 3. X-отчет
**POST** `/api/x-report`

Печатает X-отчет (отчет без гашения).

#### Параметры запроса:
```json
{}
```

---

### 4. Получение веса
**POST** `/api/get-weight`

Получает текущий вес с весов.

#### Параметры запроса:
```json
{}
```

#### Формат ответа:
```json
{
  "success": true,
  "data": {
    "weight": "string"  // Значение веса
  }
}
```

---

### 5. Внесение наличных
**POST** `/api/cash-in`

Выполняет операцию внесения наличных в кассу.

#### Параметры запроса:
```json
{
  "cashier": "string",  // ФИО кассира
  "amount": number      // Сумма внесения
}
```

#### Пример запроса:
```json
{
  "cashier": "Иван Иванов",
  "amount": 500.00
}
```

---

### 6. Выплата наличных
**POST** `/api/cash-out`

Выполняет операцию выплаты наличных из кассы.

#### Параметры запроса:
```json
{
  "cashier": "string",  // ФИО кассира
  "amount": number      // Сумма выплаты
}
```

#### Пример запроса:
```json
{
  "cashier": "Иван Иванов",
  "amount": 100.00
}
```

---

### 7. Банковские операции
**POST** `/api/bank-operation`

Выполняет операции с банковским терминалом.

#### Параметры запроса:
```json
{
  "operation": "string",  // Тип операции
  "params": {}            // Параметры операции
}
```

#### Поддерживаемые операции:

##### Оплата (PayMoney)
```json
{
  "operation": "PayMoney",
  "params": {
    "amount": number  // Сумма оплаты
  }
}
```

##### Возврат (ReturnMoney)
```json
{
  "operation": "ReturnMoney",
  "params": {
    "amount": number,        // Сумма возврата
    "checkNumber": "string"  // Номер чека
  }
}
```

##### Закрытие банковской смены (CloseShiftTerminal)
```json
{
  "operation": "CloseShiftTerminal",
  "params": {}
}
```

#### Формат ответа для банковских операций:
При успешном выполнении операции в ответе содержится массив строк банковского слипа:
```json
{
  "success": true,
  "data": {
    "success": true,
    "response": ["строка 1", "строка 2", "..."]
  }
}
```

---

### 8. Печать банковского слипа
**POST** `/api/print-bank-slip`

Печатает банковский слип на кассовом аппарате.

#### Параметры запроса:
```json
{
  "slipLines": ["array of strings"]  // Массив строк для печати
}
```

#### Пример запроса:
```json
{
  "slipLines": [
    "БАНКОВСКИЙ СЛИП",
    "Сумма: 750.00",
    "Карта: ****1234",
    "Авторизация: 123456"
  ]
}
```

---

### 9. Получение версии программы
**GET** `/api/version`

Получает версию программы сервиса.

#### Параметры запроса:
Не требуются.

#### Формат ответа:
Может возвращать либо JSON, либо простую строку с версией.

---

## Рабочий процесс с банковскими операциями

1. **Выполните банковскую операцию** (PayMoney, ReturnMoney, CloseShiftTerminal)
2. **Сохраните полученные строки слипа** из поля `response`
3. **Напечатайте слип** используя эндпоинт `/api/print-bank-slip`

### Пример последовательности:

```javascript
// 1. Выполняем оплату
const paymentResponse = await fetch('/api/bank-operation', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    operation: 'PayMoney',
    params: { amount: 750.00 }
  })
});

const paymentData = await paymentResponse.json();

// 2. Сохраняем строки слипа
let slipLines = [];
if (paymentData.success && paymentData.data && paymentData.data.success) {
  slipLines = paymentData.data.response;
}

// 3. Печатаем слип
if (slipLines.length > 0) {
  const printResponse = await fetch('/api/print-bank-slip', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ slipLines: slipLines })
  });
}
```

---

## Обработка ошибок

### Типы ошибок:
1. **Ошибки сети/соединения** - проверьте доступность сервиса
2. **Ошибки отправки команды** - `success: false` в основном ответе
3. **Ошибки выполнения на устройстве** - `data.success: false`

### Рекомендуемая обработка:
```javascript
try {
  const response = await fetch('/api/endpoint', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  });
  
  const result = await response.json();
  
  if (!result.success) {
    // Ошибка отправки команды на устройство
    console.error('Command send error:', result.message);
    return;
  }
  
  if (result.data && !result.data.success) {
    // Ошибка выполнения на устройстве
    console.error('Device operation error:', result.data.response);
    return;
  }
  
  // Успешное выполнение
  console.log('Success:', result.data.response);
  
} catch (error) {
  // Ошибка сети
  console.error('Network error:', error.message);
}
```

---

## Примечания

- Сервис работает на порту 8000 (по умолчанию)
- По адресу `http://localhost:8000` доступна страница настроек сервиса
- Все денежные суммы передаются как числа с плавающей точкой
- Строки должны быть в кодировке UTF-8
- При работе с банковскими операциями обязательно сохраняйте слипы для последующей печати
- Рекомендуется реализовать проверку доступности сервиса через эндпоинт `/api/version`