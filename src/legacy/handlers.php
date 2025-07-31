<?php
// handlers.php

require_once 'models.php';   // Здесь структура CheckData и ApiResponse
require_once 'validators.php'; // Новый валидатор
require_once 'CheckService.php'; // Новый сервис
require_once 'chznaklrr.php'; // Подключаем честный знак РР
require_once 'logger.php'; // Подключаем логгер


class Handler {
    private $checkService;
    private $logger; // Добавляем свойство для логгера

    public function __construct(CheckService $checkService, Logger $logger) {
        $this->checkService = $checkService;
        $this->logger = $logger; // Инициализируем логгер
    }

    public function HandlePrintCheck() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->logger->warning("HandlePrintCheck: Неподдерживаемый метод запроса " . $_SERVER['REQUEST_METHOD']);
            http_response_code(405);
            $this->sendHandlerResponse("error", 'Метод не поддерживается');
            return;
        }

        $input = file_get_contents('php://input');
        $checkData = json_decode($input, true);

        $validationResult = Validator::validateCheckData($checkData);
        if (!$validationResult['success']) {
            $this->logger->error("HandlePrintCheck: Ошибка валидации данных чека: " . $validationResult['message']);
            http_response_code(400);
            $this->sendHandlerResponse("error", $validationResult['message']);
            return;
        }

        $result = $this->checkService->processPrintCheck($checkData);
        if (!$result['success']) {
            $this->logger->error("HandlePrintCheck: Ошибка печати чека: " . $result['message']);
            http_response_code(500);
            $this->sendHandlerResponse("error", $result['message']);
            return;
        }

        $this->logger->info("HandlePrintCheck: Чек успешно напечатан.");
        $this->sendHandlerResponse("success", "Чек успешно напечатан", $result['data']);
    }

    public function HandleCloseShift() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->logger->warning("HandleCloseShift: Неподдерживаемый метод запроса " . $_SERVER['REQUEST_METHOD']);
            http_response_code(405);
            $this->sendHandlerResponse("error", 'Метод не поддерживается');
            return;
        }

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        $cashier = $data['cashier'] ?? ''; // Извлекаем значение cashier из входных данных

        if (empty($cashier)) {
            $this->logger->error("HandleCloseShift: Отсутствует или пустое значение cashier.");
            http_response_code(400);
            $this->sendHandlerResponse("error", 'Имя кассира не может быть пустым.');
            return;
        }

        $result = $this->checkService->closeShift($cashier);
        if (!$result['success']) {
            $this->logger->error("HandleCloseShift: Ошибка закрытия смены: " . $result['message']);
            http_response_code(500);
            $this->sendHandlerResponse("error", $result['message']);
            return;
        }

        $this->logger->info("HandleCloseShift: Смена закрыта.");
        $this->sendHandlerResponse("success", "Смена закрыта", $result['data']);
    }

    public function HandleXReport() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->logger->warning("HandleXReport: Неподдерживаемый метод запроса " . $_SERVER['REQUEST_METHOD']);
            http_response_code(405);
            $this->sendHandlerResponse("error", 'Метод не поддерживается');
            return;
        }
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        $cashier = $data['cashier'] ?? '';

        $result = $this->checkService->printXReport($cashier);
        if (!$result['success']) {
            $this->logger->error("HandleXReport: Ошибка печати X-отчёта: " . $result['message']);
            http_response_code(500);
            $this->sendHandlerResponse("error", $result['message']);
            return;
        }
        $this->logger->info("HandleXReport: X-отчёт напечатан.");
        $this->sendHandlerResponse("success", "X-отчёт напечатан", $result['data']);
    }

    public function HandleCashIn() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->logger->warning("HandleCashIn: Неподдерживаемый метод запроса " . $_SERVER['REQUEST_METHOD']);
            http_response_code(405);
            $this->sendHandlerResponse("error", 'Метод не поддерживается');
            return;
        }
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $amount = $data['amount'] ?? 0;
        $cashier = $data['cashier'] ?? '';

        if (empty($cashier)) {
            $this->logger->error("HandleCashIn: Отсутствует или пустое значение cashier.");
            http_response_code(400);
            $this->sendHandlerResponse("error", 'Имя кассира не может быть пустым.');
            return;
        }

        $result = $this->checkService->cashIn($cashier, $amount);
        if (!$result['success']) {
            $this->logger->error("HandleCashIn: Ошибка внесения наличных: " . $result['message']);
            http_response_code(500);
            $this->sendHandlerResponse("error", $result['message']);
            return;
        }
        $this->logger->info("HandleCashIn: Внесение наличных выполнено.");
        $this->sendHandlerResponse("success", "Внесение выполнено", $result['data']);
    }

    public function HandleCashOut() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->logger->warning("HandleCashOut: Неподдерживаемый метод запроса " . $_SERVER['REQUEST_METHOD']);
            http_response_code(405);
            $this->sendHandlerResponse("error", 'Метод не поддерживается');
            return;
        }
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $amount = $data['amount'] ?? 0;
        $cashier = $data['cashier'] ?? '';

        if (empty($cashier)) {
            $this->logger->error("HandleCashOut: Отсутствует или пустое значение cashier.");
            http_response_code(400);
            $this->sendHandlerResponse("error", 'Имя кассира не может быть пустым.');
            return;
        }

        $result = $this->checkService->cashOut($cashier, $amount);
        if (!$result['success']) {
            $this->logger->error("HandleCashOut: Ошибка выплаты наличных: " . $result['message']);
            http_response_code(500);
            $this->sendHandlerResponse("error", $result['message']);
            return;
        }
        $this->logger->info("HandleCashOut: Выплата наличных выполнена.");
        $this->sendHandlerResponse("success", "Выплата выполнена", $result['data']);
    }


    public function HandlePrintBankSlip() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->logger->warning("HandlePrintBankSlip: Неподдерживаемый метод запроса " . $_SERVER['REQUEST_METHOD']);
            http_response_code(405);
            $this->sendHandlerResponse("error", 'Метод не поддерживается');
            return;
        }
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $slipLines = $data['slipLines'] ?? []; // Предполагаем, что slipLines это массив строк

        $result = $this->checkService->printBankSlip($slipLines);
        if (!$result['success']) {
            $this->logger->error("HandlePrintBankSlip: Ошибка печати банковского слипа: " . $result['message']);
            http_response_code(500);
            $this->sendHandlerResponse("error", $result['message']);
            return;
        }
        $this->logger->info("HandlePrintBankSlip: Банковский слип напечатан.");
        $this->sendHandlerResponse("success", "Банковский слип напечатан", $result['data']);
    }

    public function HandleBankOperation() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->logger->warning("HandleBankOperation: Неподдерживаемый метод запроса " . $_SERVER['REQUEST_METHOD']);
            http_response_code(405);
            $this->sendHandlerResponse("error", 'Метод не поддерживается');
            return;
        }
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $operation = $data['operation'] ?? '';
        $params = $data['params'] ?? [];
        $result = $this->checkService->bankOperation($operation, $params);
        if (!$result['success']) {
            $this->logger->error("HandleBankOperation: Ошибка банковской операции: " . $result['message']);
            http_response_code(500);
            $this->sendHandlerResponse("error", $result['message']);
            return;
        }
        $this->logger->info("HandleBankOperation: Банковская операция была отпралена на терминал.");
        $this->sendHandlerResponse("success", "Банковская операция была отпралена на терминал", $result['data']);
    }

    public function HandleGetWeight() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->logger->warning("HandleGetWeight: Неподдерживаемый метод запроса " . $_SERVER['REQUEST_METHOD']);
            http_response_code(405);
            $this->sendHandlerResponse("error", 'Метод не поддерживается');
            return;
        }
        $result = $this->checkService->getWeight();
        if (!$result['success']) {
            $this->logger->error("HandleGetWeight: Ошибка получения веса: " . $result['message']);
            http_response_code(500);
            $this->sendHandlerResponse("error", $result['message']);
            return;
        }
        $this->logger->info("HandleGetWeight: Вес получен.");
        $this->sendHandlerResponse("success", "Вес получен", $result['data']);
    }

    public function HandleCheckRR() {
        $result = getCdnInfo();
        if ($result['success']) {
            $this->logger->info("HandleCheckRR: CDN - получены");
            $this->sendHandlerResponse("success", "CDN - получены", $result['data']);
        } else {
            $this->sendHandlerResponse("error", $result['message']);    
            $errstr = $result['message'];
            $this->logger->error("HandleCheckRR: CDN - не получены ($errstr)");
        }
        //$this->sendHandlerResponse("success", "CDN - получены", $result['data']);
    }

    public function HandleAddCheckPosition() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->logger->warning("HandleAddCheckPosition: Неподдерживаемый метод запроса " . $_SERVER['REQUEST_METHOD']);
            http_response_code(405);
            $this->sendHandlerResponse("error", 'Метод не поддерживается');
            return;
        }
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $session_id = $data['session_id'] ?? null;
        $position = $data['position'] ?? null;
        if (!$session_id || !$position) {
            http_response_code(400);
            $this->sendHandlerResponse("error", 'Не передан session_id или position');
            return;
        }
        $result = $this->checkService->addCheckPosition($session_id, $position);

        if (!$result['success']) {
            $this->logger->error("HandleAddCheckPosition: Ошибка добавления позиции: " . $result['message']);
            http_response_code(500);
            $this->sendHandlerResponse("error", $result['message']);
            return;
        }

        $this->logger->info("HandleAddCheckPosition: поизция успешно добавлена.");
        $this->sendHandlerResponse("success", "Поизция успешно добавлена", $result['data']);
    }

    private function sendHandlerResponse($type, $message, $data = [], $id = "") {
        header('Content-Type: application/json');
        $response = new ApiResponse($type, $message, $data, $id);
        $this->logger->info("Отправка ответа: " . json_encode($response->toArray(), JSON_UNESCAPED_UNICODE));
        echo json_encode($response->toArray(), JSON_UNESCAPED_UNICODE);
    }
}
