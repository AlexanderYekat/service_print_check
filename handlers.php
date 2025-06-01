<?php
// handlers.php

require_once 'kktutils.php'; // Здесь должны быть ваши функции для работы с ККТ
require_once 'models.php';   // Здесь структура CheckData и WSResponse

class Handler {
    private $comport;
    private $ipaddresskkt;
    private $portkktatol;
    private $ipaddressservrkkt;
    private $emulation;
    private $FptrDriver;
    private $version;

    public function __construct(
        $comport,
        $ipaddresskkt,
        $portkktatol,
        $ipaddressservrkkt,
        $emulation,
        $FptrDriver,
        $version
    ) {
        $this->comport = $comport;
        $this->ipaddresskkt = $ipaddresskkt;
        $this->portkktatol = $portkktatol;
        $this->ipaddressservrkkt = $ipaddressservrkkt;
        $this->emulation = $emulation;
        $this->FptrDriver = $FptrDriver;
        $this->version = $version;
    }

    public function HandlePrintCheck() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Метод не поддерживается']);
            return;
        }

        $input = file_get_contents('php://input');
        $checkData = json_decode($input, true);

        if (!isset($checkData['cashier']) || empty($checkData['cashier'])) {
            http_response_code(400);
            echo json_encode(['error' => 'не указано имя кассира']);
            return;
        }

        // Формируем JSON для ККТ
        $checkJSON = kktutils_formatCheckJSON($checkData);

        // Получаем драйвер (здесь предполагается, что FptrDriver уже инициализирован)
        $fptr = $this->FptrDriver;

        // Подключаемся к кассе
        list($ok, $typepodkluch) = kktutils_connectWithKassa(
            $fptr,
            $this->comport,
            $this->ipaddresskkt,
            $this->portkktatol,
            $this->ipaddressservrkkt
        );
        if (!$ok) {
            $this->sendHandleError("ошибка подключения к кассе: $typepodkluch");
            return;
        }

        // Печатаем чек
        list($result, $err) = kktutils_sendCommandAndGetAnswerFromKKT($fptr, $checkJSON, $this->emulation);
        if ($err) {
            $this->sendHandleError("ошибка при печати чека: $err");
            kktutils_closeKassa($fptr);
            return;
        }

        if (!kktutils_successCommand($result)) {
            $this->sendHandleError("ошибка при печати чека: $result");
            kktutils_closeKassa($fptr);
            return;
        }

        // Парсим результат и получаем fiscalDocumentNumber
        $resultJSON = json_decode($result, true);
        $fiscalDocumentNumber = 0;
        if (isset($resultJSON['fiscalParams']['fiscalDocumentNumber'])) {
            $fiscalDocumentNumber = $resultJSON['fiscalParams']['fiscalDocumentNumber'];
        } elseif ($this->emulation) {
            $fiscalDocumentNumber = 123;
        }

        $this->sendHandlerResponse("success", "Чек успешно напечатан", [
            "fiscalDocumentNumber" => $fiscalDocumentNumber
        ]);

        kktutils_closeKassa($fptr);
    }

    public function HandleCloseShift() {
        // Реализуйте по аналогии с HandlePrintCheck
        $this->sendHandlerResponse("success", "Смена закрыта", []);
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
