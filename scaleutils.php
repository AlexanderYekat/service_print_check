<?php
// scaleutils.php

class TScale8Driver {
    private $scale = null;
    private $comPort; // Номер COM-порта
    private $baudRate; // Скорость передачи данных (BaudRate)
    private $model;    // Модель весов
    private $emulation; // Флаг эмуляции
    private $logger; // Добавляем свойство для логгера

    public function __construct(int $comPort = 1001, int $baudRate = 18, int $model = 38, bool $emulation = false, ?Logger $logger = null) {
        $this->comPort = $comPort;
        $this->baudRate = $baudRate;
        $this->model = $model;
        $this->emulation = $emulation;
        $this->logger = $logger ?? Logger::getInstance(); // Инициализируем логгер или получаем существующий экземпляр
    }

    public function Open(): array {
        $this->logger->info("Попытка открытия соединения с весами. Эмуляция: " . ($this->emulation ? 'Да' : 'Нет'));

        try {
            if ($this->scale === null) {
                // Создание COM-объекта AddIn.Scale8
                $this->scale = new COM("AddIn.Scale8");
            }

            // Проверяем количество устройств и добавляем, если нет ни одного
            try {
                $deviceCount = $this->scale->DeviceCount;
                $this->logger->info("Обнаружено устройств: {$deviceCount}");
                if ($deviceCount == 0) {
                    $this->logger->info("Устройств не найдено, попытка добавления устройства...");
                    $addResult = $this->scale->AddDevice();
                    if ($addResult === 0) { // Обычно 0 означает успех
                        $this->logger->info("Устройство успешно добавлено.");
                    } else {
                        $addResultDescription = $this->scale->ResultDescription;
                        $addResultDescription = iconv('Windows-1251', 'UTF-8//IGNORE', $addResultDescription);
                        $this->logger->error("Ошибка при добавлении устройства: {$addResultDescription} (Код: {$addResult})");
                        if (!$this->emulation) {
                            return [false, "Ошибка при добавлении устройства: {$addResultDescription}"];
                        }
                    }
                }
            } catch (Exception $e) {
                $this->logger->error("Ошибка при проверке/добавлении устройства: " . $e->getMessage());
                if (!$this->emulation) {
                    return [false, "Ошибка при проверке/добавлении устройства: " . $e->getMessage()];
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
            $this->logger->info("Соединение с весами успешно открыто.");
            return [true, ""];
        } catch (Exception $e) {
            $this->logger->error("Ошибка создания COM объекта весов: " . $e->getMessage());
            return [false, "Драйвер весов не установлен: " . $e->getMessage()];
        }
    }

    public function ReadWeight(): array {
        $this->logger->info("Попытка чтения веса. Эмуляция: " . ($this->emulation ? 'Да' : 'Нет'));

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
                } else {
                    return [true, "", 5.0];
                }
            }
        } catch (Exception $e) {
            $this->logger->error("Ошибка при чтении веса: " . $e->getMessage());
            if (!$this->emulation) {
                return [false, "Ошибка при чтении веса: " . $e->getMessage(), 0.0];
            } else {
                return [true, "", 5.0];
            }
        }
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