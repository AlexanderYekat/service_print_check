<?php
// scaleutils.php

class TScale8Driver {
    private $scale = null;
    private $comPort; // Номер COM-порта
    private $baudRate; // Скорость передачи данных (BaudRate)
    private $model;    // Модель весов

    public function __construct(int $comPort = 1000, int $baudRate = 18, int $model = 38) {
        $this->comPort = $comPort;
        $this->baudRate = $baudRate;
        $this->model = $model;
    }

    public function Open(): array {
        try {
            if ($this->scale === null) {
                // Создание COM-объекта AddIn.Scale8
                $this->scale = new COM("AddIn.Scale8") or die("Не удалось создать объект драйвера весов AddIn.Scale8");
            }

            // Установка параметров
            $this->scale->PortNumber = $this->comPort;
            $this->scale->BaudRate = $this->baudRate;
            $this->scale->Model = $this->model; // Атол Марта

            // Включение устройства
            $this->scale->DeviceEnabled = true;

            $resultDescription = $this->scale->ResultDescription;
            $resultDescription = iconv('Windows-1251', 'UTF-8//IGNORE', $resultDescription);

            if (!$this->scale->DeviceEnabled) {
                return [false, "Весы не подключены: {$resultDescription}"];
            }
            return [true, ""];
        } catch (Exception $e) {
            return [false, "Ошибка открытия соединения с весами: " . $e->getMessage()];
        }
    }

    public function ReadWeight(): array {
        if ($this->scale === null) {
            return [false, "Драйвер весов не инициализирован", 0.0];
        }

        try {
            // Вызов метода ReadWeight
            $result = $this->scale->ReadWeight();

            if ($result === 0) { // Если успешно
                $weight = $this->scale->Weight; // Получение веса
                return [true, "", $weight];
            } else {
                $resultDescription = $this->scale->ResultDescription;
                $resultDescription = iconv('Windows-1251', 'UTF-8//IGNORE', $resultDescription);
                return [false, "Ошибка получения веса: {$resultDescription}", 0.0];
            }
        } catch (Exception $e) {
            return [false, "Ошибка при чтении веса: " . $e->getMessage(), 0.0];
        }
    }

    public function Close(): void {
        if ($this->scale === null) {
            return;
        }
        try {
            $this->scale->DeviceEnabled = false; // Отключаем устройство
        } catch (Exception $e) {
            // Игнорируем ошибки при закрытии
        }
    }
} 