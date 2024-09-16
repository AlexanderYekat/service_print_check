# Service Print Check

## Описание проекта

Проект представляет собой сервис для взаимодействия с фискальными регистраторами, в частности моделями ATOL. Он предоставляет WebSocket API для получения команд на печать чеков, закрытие смен и генерацию X-отчетов. Сервис управляет подключением к фискальному регистратору, отправкой команд в формате JSON и обработкой ошибок.

## Установка как службы Windows

1. Скомпилируйте приложение:
   ```sh
   go build jsontokkt.go
   ```
2. Установите службу с помощью утилиты sc:
   ```sh
   sc create CloudPosBridge binPath= "path\to\jsontokkt.exe"
   ```
3. Запустите службу:
   ```sh
   sc start CloudPosBridge
   ```

## Флаги командной строки

- `-clearlogs`: Очистить логи программы (по умолчанию true)
- `-debug`: Уровень логирования (по умолчанию 3)
- `-com`: COM-порт кассы
- `-cassir`: Имя кассира
- `-ipkkt`: IP-адрес ККТ
- `-portipkkt`: Порт IP ККТ
- `-ipservkkt`: IP-адрес сервера ККТ
- `-emul`: Режим эмуляции

## API

Сервис предоставляет WebSocket API для выполнения следующих операций:

- `printCheck`: Печать чека на основе предоставленных данных в формате JSON.
- `closeShift`: Закрытие текущей смены с указанным именем кассира.
- `xReport`: Печать X-отчета.

Подробная документация по API доступна в коде проекта. Пример взаимодействия с сервисом на JavaScript представлен в папке `samples/javascript`.

## Бизнес-логика

- Сервис обрабатывает команды на печать чеков, закрытие смен и генерацию X-отчетов.
- Управляет подключением к фискальному регистратору и отправкой команд в формате JSON.
- Включает функциональность для проверки статуса фискального регистратора, открытия и закрытия смен, а также обработки ошибок.
- Сервис может работать как служба Windows или как автономное приложение.

## Используемые технологии

| Название                        | Версия   |
| ------------------------------- | -------- |
| Go                              | 1.21.3   |
| gorilla/websocket               | 1.5.1    |
| golang.org/x/sys/windows        | v0.0.0-20230907160148-10611013b981 |
| github.com/gorilla/websocket    | 1.5.1    |

## Разработка

Проект использует модули Go для управления зависимостями. Для добавления новых зависимостей используйте:

```sh
go get package-name
```

## Лицензия

[MIT License](LICENSE)

## Контакты

Для вопросов и предложений, пожалуйста, создайте issue в репозитории проекта.

### Примечания

- Убедитесь, что библиотека `libfptr10` установлена и доступна.
- Сервис слушает на порту 8081 для WebSocket подключений.
- Логи сервиса сохраняются в директории `%ProgramData%\CloudPosBridge\logs`.

### Последовательности выполнения операций

#### Печать чека
```sequence
Client->>Service: printCheck(checkData)
Service->>libfptr10: connect()
Service->>libfptr10: checkOpenShift()
Service->>libfptr10: sendCommand(printCheckJSON)
Service->>Client: printCheckResponse(fiscalDocumentNumber)
```

#### Закрытие смены
```sequence
Client->>Service: closeShift(cashier)
Service->>libfptr10: connect()
Service->>libfptr10: sendCommand(closeShiftJSON)
Service->>Client: closeShiftResponse()
```

#### Печать X-отчета
```sequence
Client->>Service: xReport()
Service->>libfptr10: connect()
Service->>libfptr10: sendCommand(xReportJSON)
Service->>Client: xReportResponse()
```

### Пример взаимодействия с сервисом на JavaScript

Пример кода на JavaScript для подключения к WebSocket API и отправки команд находится в папке `samples/javascript`.

---

Теперь файл `README.md` содержит более полную информацию о проекте. Если у вас есть дополнительные пожелания или вопросы, дайте знать!
