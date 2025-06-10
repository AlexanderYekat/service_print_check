<?php
// scaleutils.php

class TScale8Driver {
    private $scale = null;
    private $comPort; // Номер COM-порта
    private $baudRate; // Скорость передачи данных (BaudRate)
    private $model;    // Модель весов
    private $emulation; // Флаг эмуляции
    private $logger; // Добавляем свойство для логгера

    public function __construct(int $comPort = 1000, int $baudRate = 18, int $model = 38, bool $emulation = false, Logger $logger = null) {
        $this->comPort = $comPort;
        $this->baudRate = $baudRate;
        $this->model = $model;
        $this->emulation = $emulation;
        $this->logger = $logger;
    }

    public function Open(): array {
        $this->logger->info("Попытка открытия соединения с весами. Эмуляция: " . ($this->emulation ? 'Да' : 'Нет'));

        try {
            if ($this->scale === null) {
                // Создание COM-объекта AddIn.Scale8
                $this->scale = new COM("AddIn.Scale8");
                if ($this->scale === null) {
                    $this->logger->error("Не удалось создать объект драйвера весов AddIn.Scale8");
                    if (!$this->emulation) {
                        return [false, "Не удалось создать объект драйвера весов AddIn.Scale8"];
                    }
                }
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
                $this->logger->error("Весы не подключены: {$resultDescription}");
                if (!$this->emulation) {
                    return [false, "Весы не подключены: {$resultDescription}"];
                }
            }
            return [true, ""];
        } catch (Exception $e) {
            $this->logger->error("Ошибка открытия соединения с весами: " . $e->getMessage());
            if ($this->emulation) {
                return [true, ""]; // Успешное открытие в режиме эмуляции
            }        
            return [false, "Ошибка открытия соединения с весами: " . $e->getMessage()];
        }
    }

    public function ReadWeight(): array {
        if ($this->scale === null) {
            $this->logger->error("Драйвер весов не инициализирован для чтения веса.");
            if (!$this->emulation) {
                return [false, "Драйвер весов не инициализирован", 0.0];
            }
        }

        try {
            $this->logger->info("Вызов метода ReadWeight COM-объекта.");
            // Вызов метода ReadWeight
            $result = $this->scale->ReadWeight();

            if ($result === 0) { // Если успешно
                $weight = $this->scale->Weight; // Получение веса
                $this->logger->info("Вес успешно прочитан: {$weight}.");
                return [true, "", $weight];
            } else {
                $resultDescription = $this->scale->ResultDescription;
                $resultDescription = iconv('Windows-1251', 'UTF-8//IGNORE', $resultDescription);
                $this->logger->error("Ошибка получения веса: {$resultDescription}.");
                if (!$this->emulation) {
                    return [false, "Ошибка получения веса: {$resultDescription}", 0.0];
                }
            }
        } catch (Exception $e) {
            $this->logger->error("Ошибка при чтении веса: " . $e->getMessage());
            if (!$this->emulation) {
                return [false, "Ошибка при чтении веса: " . $e->getMessage(), 0.0];
            }
        }
        return [true, "", 1.344];
    }

    public function Close(): void {
        if ($this->scale === null) {
            $this->logger->warning("Попытка закрыть неинициализированный драйвер весов.");
            return;
        }
        try {
            $this->logger->info("Попытка закрытия соединения с весами.");
            $this->scale->DeviceEnabled = false; // Отключаем устройство
            $this->logger->info("Соединение с весами успешно закрыто.");
        } catch (Exception $e) {
            $this->logger->error("Ошибка при закрытии соединения с весами: " . $e->getMessage());
            // Игнорируем ошибки при закрытии
        }
    }
} 