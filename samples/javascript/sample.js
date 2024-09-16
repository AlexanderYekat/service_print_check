let ws;
let lastErrorNotificationTime = 0;

function connectWebSocket() {
    ws = new WebSocket('ws://localhost:8081/ws');

    ws.onopen = function() {
        console.log('WebSocket соединение установлено');
    };

    ws.onmessage = function(event) {
        const response = JSON.parse(event.data);
        console.log(response)
        if (response.type === 'error') {
            showNotification(response.message, 'error');
        } else {
            let message = response.message;
            if (response.data !== undefined && response.data !== 0) {
                message += ` (Номер чека: ${response.data})`;
            }
            showNotification(message, 'success');
        }
    };

    ws.onerror = function(error) {
        console.error('Ошибка WebSocket:', error);
        const currentTime = Date.now();
        if (currentTime - lastErrorNotificationTime > 120000) { // 120000 мс = 2 минуты
            showNotification(`Ошибка подключения кассового аппарата (Ошибка WebSocket: ${error})`, 'error');
            lastErrorNotificationTime = currentTime;
        }
    };

    ws.onclose = function() {
        console.log('WebSocket соединение закрыто');
        setTimeout(connectWebSocket, 5000); // Попытка переподключения через 5 секунд
    };
}

// Вызовите эту функцию при загрузке страницы
document.addEventListener('DOMContentLoaded', connectWebSocket);

function printCheck() {
    const checkData = {
        command: 'printCheck',
        data: {
            tableData: [
                {
                    name: 'Товар 1',
                    quantity: '2',
                    price: '100.00'
                },
                {
                    name: 'Товар 2',
                    quantity: '1',
                    price: '200.00'
                }
            ],
            cashier: 'Иван Иванов',
            payments: [
                {
                    type: 'cash',
                    amount: 300.00
                },
                {
                    type: 'electronically',
                    amount: 100.00
                }
            ],
            type: 'sell' //продажа sellReturn - возрат
        }
    };

    socket.send(JSON.stringify(checkData));
}

function closeShift() {
    const closeShiftData = {
        command: 'closeShift',
        data: {
            cashier: 'Иван Иванов'
        }
    };

    socket.send(JSON.stringify(closeShiftData));
}

function printXReport() {
    const xReportData = {
        command: 'xReport'
    };

    socket.send(JSON.stringify(xReportData));
}