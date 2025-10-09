<?php
// TaskManager.php - Управление асинхронными задачами

define('TASKS_DIR', __DIR__ . '/tasks');

class TaskManager {
    private $logger;
    private $tasksDir;

    public function __construct(Logger $logger, $tasksDir = TASKS_DIR) {
        $this->logger = $logger;
        $this->tasksDir = $tasksDir;
        
        // Создаем директорию для задач, если её нет
        if (!is_dir($this->tasksDir)) {
            mkdir($this->tasksDir, 0777, true);
            $this->logger->info("Создана директория для задач: {$this->tasksDir}");
        }
    }

    /**
     * Генерирует уникальный ID задачи
     * @return string UUID v4
     */
    public function generateTaskId() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Создает новую задачу
     * @param string $type Тип задачи (например, 'check_marking_code')
     * @param array $params Параметры задачи
     * @return array ['success' => bool, 'taskId' => string, 'message' => string]
     */
    public function createTask($type, $params) {
        try {
            $taskId = $this->generateTaskId();
            $taskFile = $this->getTaskFilePath($taskId);
            
            $task = [
                'id' => $taskId,
                'type' => $type,
                'status' => 'pending', // pending, processing, completed, error
                'params' => $params,
                'result' => null,
                'error' => null,
                'createdAt' => date('Y-m-d H:i:s'),
                'updatedAt' => date('Y-m-d H:i:s')
            ];
            
            $result = file_put_contents($taskFile, json_encode($task, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
            if ($result === false) {
                $this->logger->error("Не удалось создать файл задачи: {$taskFile}");
                return ['success' => false, 'message' => 'Не удалось создать задачу'];
            }
            
            $this->logger->info("Создана задача {$taskId} типа {$type}");
            return ['success' => true, 'taskId' => $taskId, 'message' => 'Задача создана'];
            
        } catch (Exception $e) {
            $this->logger->error("Ошибка при создании задачи: " . $e->getMessage());
            return ['success' => false, 'message' => 'Ошибка при создании задачи: ' . $e->getMessage()];
        }
    }

    /**
     * Получает задачу по ID
     * @param string $taskId ID задачи
     * @return array|null Данные задачи или null если не найдена
     */
    public function getTask($taskId) {
        try {
            $taskFile = $this->getTaskFilePath($taskId);
            
            if (!file_exists($taskFile)) {
                $this->logger->warning("Задача {$taskId} не найдена");
                return null;
            }
            
            $content = file_get_contents($taskFile);
            if ($content === false) {
                $this->logger->error("Не удалось прочитать файл задачи: {$taskFile}");
                return null;
            }
            
            $task = json_decode($content, true);
            if ($task === null) {
                $this->logger->error("Не удалось декодировать JSON задачи {$taskId}");
                return null;
            }
            
            return $task;
            
        } catch (Exception $e) {
            $this->logger->error("Ошибка при получении задачи {$taskId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Обновляет статус задачи
     * @param string $taskId ID задачи
     * @param string $status Новый статус
     * @param array|null $result Результат выполнения (если есть)
     * @param string|null $error Ошибка (если есть)
     * @return bool Успешность операции
     */
    public function updateTask($taskId, $status, $result = null, $error = null) {
        try {
            $task = $this->getTask($taskId);
            if ($task === null) {
                return false;
            }
            
            $task['status'] = $status;
            $task['updatedAt'] = date('Y-m-d H:i:s');
            
            if ($result !== null) {
                $task['result'] = $result;
            }
            
            if ($error !== null) {
                $task['error'] = $error;
            }
            
            $taskFile = $this->getTaskFilePath($taskId);
            $saveResult = file_put_contents($taskFile, json_encode($task, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
            if ($saveResult === false) {
                $this->logger->error("Не удалось сохранить обновленную задачу {$taskId}");
                return false;
            }
            
            $this->logger->info("Обновлена задача {$taskId}: статус={$status}");
            return true;
            
        } catch (Exception $e) {
            $this->logger->error("Ошибка при обновлении задачи {$taskId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Удаляет задачу (опционально, для очистки старых задач)
     * @param string $taskId ID задачи
     * @return bool Успешность операции
     */
    public function deleteTask($taskId) {
        try {
            $taskFile = $this->getTaskFilePath($taskId);
            
            if (!file_exists($taskFile)) {
                return true; // Задача уже не существует
            }
            
            if (unlink($taskFile)) {
                $this->logger->info("Удалена задача {$taskId}");
                return true;
            } else {
                $this->logger->error("Не удалось удалить задачу {$taskId}");
                return false;
            }
            
        } catch (Exception $e) {
            $this->logger->error("Ошибка при удалении задачи {$taskId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Очищает старые задачи (старше указанного времени)
     * @param int $olderThanSeconds Удалить задачи старше указанного количества секунд (по умолчанию 24 часа)
     * @return int Количество удаленных задач
     */
    public function cleanupOldTasks($olderThanSeconds = 86400) {
        try {
            $deleted = 0;
            $files = glob($this->tasksDir . '/*.json');
            
            if ($files === false) {
                return 0;
            }
            
            $currentTime = time();
            
            foreach ($files as $file) {
                $fileTime = filemtime($file);
                if ($fileTime !== false && ($currentTime - $fileTime) > $olderThanSeconds) {
                    if (unlink($file)) {
                        $deleted++;
                        $this->logger->debug("Удален старый файл задачи: {$file}");
                    }
                }
            }
            
            if ($deleted > 0) {
                $this->logger->info("Очищено старых задач: {$deleted}");
            }
            
            return $deleted;
            
        } catch (Exception $e) {
            $this->logger->error("Ошибка при очистке старых задач: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Получает путь к файлу задачи
     * @param string $taskId ID задачи
     * @return string Путь к файлу
     */
    private function getTaskFilePath($taskId) {
        return $this->tasksDir . DIRECTORY_SEPARATOR . $taskId . '.json';
    }

    /**
     * Получает список всех задач (для отладки)
     * @return array Массив задач
     */
    public function getAllTasks() {
        try {
            $tasks = [];
            $files = glob($this->tasksDir . '/*.json');
            
            if ($files === false) {
                return [];
            }
            
            foreach ($files as $file) {
                $content = file_get_contents($file);
                if ($content !== false) {
                    $task = json_decode($content, true);
                    if ($task !== null) {
                        $tasks[] = $task;
                    }
                }
            }
            
            return $tasks;
            
        } catch (Exception $e) {
            $this->logger->error("Ошибка при получении списка всех задач: " . $e->getMessage());
            return [];
        }
    }
}

