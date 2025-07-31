<?php
// validators.php

class Validator {
    public static function validateCheckData($data) {
        if (!isset($data['cashier']) || empty($data['cashier'])) {
            return [
                'success' => false,
                'message' => 'не указано имя кассира'
            ];
        }
        // Можно добавить дополнительные проверки (например, tableData, payments и т.д.)
        return ['success' => true];
    }
} 