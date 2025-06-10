<?php
// handlers.php

require_once 'models.php';   // Здесь структура CheckData и WSResponse
require_once 'validators.php'; // Новый валидатор
require_once 'CheckService.php'; // Новый сервис

class Handler {
    private $checkService;

    public function __construct(CheckService $checkService) {
        $this->checkService = $checkService;
    }

    public function HandlePrintCheck() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Метод не поддерживается']);
            return;
        }

        $input = file_get_contents('php://input');
        $checkData = json_decode($input, true);

        $validationResult = Validator::validateCheckData($checkData);
        if (!$validationResult['success']) {
            http_response_code(400);
            echo json_encode(['error' => $validationResult['message']]);
            return;
        }

        $result = $this->checkService->printCheck($checkData);
        if (!$result['success']) {
            http_response_code(500);
            echo json_encode(['error' => $result['message']]);
            return;
        }

        $this->sendHandlerResponse("success", "Чек успешно напечатан", $result['data']);
    }

    public function HandleCloseShift() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Метод не поддерживается']);
            return;
        }

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        $result = $this->checkService->closeShift();
        if (!$result['success']) {
            http_response_code(500);
            echo json_encode(['error' => $result['message']]);
            return;
        }

        $this->sendHandlerResponse("success", "Смена закрыта", $result['data']);
    }

    public function HandleXReport() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Метод не поддерживается']);
            return;
        }
        $result = $this->checkService->printXReport();
        if (!$result['success']) {
            http_response_code(500);
            echo json_encode(['error' => $result['message']]);
            return;
        }
        $this->sendHandlerResponse("success", "X-отчёт напечатан", $result['data']);
    }

    public function HandleCashIn() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Метод не поддерживается']);
            return;
        }
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $amount = $data['amount'] ?? 0;
        $result = $this->checkService->cashIn($amount);
        if (!$result['success']) {
            http_response_code(500);
            echo json_encode(['error' => $result['message']]);
            return;
        }
        $this->sendHandlerResponse("success", "Внесение выполнено", $result['data']);
    }

    public function HandleCashOut() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Метод не поддерживается']);
            return;
        }
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $amount = $data['amount'] ?? 0;
        $result = $this->checkService->cashOut($amount);
        if (!$result['success']) {
            http_response_code(500);
            echo json_encode(['error' => $result['message']]);
            return;
        }
        $this->sendHandlerResponse("success", "Выплата выполнена", $result['data']);
    }

    public function HandleBankOperation() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Метод не поддерживается']);
            return;
        }
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $operation = $data['operation'] ?? '';
        $params = $data['params'] ?? [];
        $result = $this->checkService->bankOperation(null, $operation, $params);
        if (!$result['success']) {
            http_response_code(500);
            echo json_encode(['error' => $result['message']]);
            return;
        }
        $this->sendHandlerResponse("success", "Банковская операция выполнена", $result['data']);
    }

    public function HandleGetWeight() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Метод не поддерживается']);
            return;
        }
        $result = $this->checkService->getWeight(null);
        if (!$result['success']) {
            http_response_code(500);
            echo json_encode(['error' => $result['message']]);
            return;
        }
        $this->sendHandlerResponse("success", "Вес получен", $result['data']);
    }

    public function HandlePrintBankSlip() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Метод не поддерживается']);
            return;
        }
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $slipLines = $data['slipLines'] ?? []; // Предполагаем, что slipLines это массив строк

        $result = $this->checkService->printBankSlip($slipLines);
        if (!$result['success']) {
            http_response_code(500);
            echo json_encode(['error' => $result['message']]);
            return;
        }
        $this->sendHandlerResponse("success", "Банковский слип напечатан", $result['data']);
    }

    public function HandleReturnMany() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Метод не поддерживается']);
            return;
        }
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $params = $data['params'] ?? [];

        $result = $this->checkService->returnMany(null, $params);
        if (!$result['success']) {
            http_response_code(500);
            echo json_encode(['error' => $result['message']]);
            return;
        }
        $this->sendHandlerResponse("success", "Возврат по безналу выполнен", $result['data']);
    }

    public function HandleCloseBankShift() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Метод не поддерживается']);
            return;
        }

        $result = $this->checkService->closeBankShift(null);
        if (!$result['success']) {
            http_response_code(500);
            echo json_encode(['error' => $result['message']]);
            return;
        }
        $this->sendHandlerResponse("success", "Банковская смена закрыта", $result['data']);
    }

    private function sendHandleError($message) {
        $response = [
            "type" => "error",
            "message" => $message,
            "id" => "",
            "time" => round(microtime(true) * 1000)
        ];
        http_response_code(200);
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }

    private function sendHandlerResponse($type, $message, $data = [], $id = "") {
        $response = [
            "type" => $type,
            "message" => $message,
            "data" => $data,
            "id" => $id,
            "time" => round(microtime(true) * 1000)
        ];
        http_response_code(200);
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }
}
