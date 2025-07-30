<?php
// Единая точка входа для сервера с чистой архитектурой

// Определяем, какую версию использовать
$useCleanArchitecture = true; // Переключатель для постепенного перехода

if ($useCleanArchitecture) {
    require_once __DIR__ . '/../src/routes.php';
    handleCleanArchitectureRequest();
} else {
    // Старая система (для обратной совместимости)
    require_once __DIR__ . '/../src/atolservice.php'; 
    handleHttpRequest();
}