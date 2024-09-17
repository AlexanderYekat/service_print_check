<?php

// Подключение необходимых библиотек
require_once 'vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

// Определение констант
define('VERSION_OF_PROGRAM', '2024_09_16_02');

// Классы для работы с данными
class CheckItem {
    public $name;
    public $quantity;
    public $price;
}

class Payment {
    public $type;
    public $amount;
}

class CheckData {
    public $tableData;
    public $cashier;
    public $payments;
    public $type;
}

// Класс для обработки WebSocket соединений
class WebSocketHandler implements MessageComponentInterface {
    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "Новое соединение! (" . $conn . ")\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg);
        switch ($data->command) {
            case 'printCheck':
                $result = $this->printCheck($data->data);
                $from->send(json_encode($result));
                break;
            case 'closeShift':
                $result = $this->closeShift($data->data->cashier);
                $from->send(json_encode($result));
                break;
            case 'xReport':
                $result = $this->printXReport();
                $from->send(json_encode($result));
                break;
            default:
                echo "Unknown command: {$data->command}\n";
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection {. .} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    protected function printCheck($checkData) {
        // Реализация печати чека
        // Здесь нужно будет использовать специфическую библиотеку для работы с кассовым аппаратом
        return ['status' => 'success', 'message' => 'Чек успешно напечатан', 'data' => 123];
    }

    protected function closeShift($cashier) {
        // Реализация закрытия смены
        return ['status' => 'success', 'message' => 'Смена успешно закрыта'];
    }

    protected function printXReport() {
        // Реализация печати X-отчета
        return ['status' => 'success', 'message' => 'X-отчет успешно напечатан'];
    }
}

// Запуск WebSocket сервера
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new WebSocketHandler()
        )
    ),
    8081
);

echo "WebSocket server running on port 8081\n";
$server->run();

?>
