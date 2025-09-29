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
        
        // Проверяем, что для маркированных товаров указана категория
        if (isset($data['tableData']) && is_array($data['tableData'])) {
            foreach ($data['tableData'] as $index => $item) {
                // Если товар имеет маркировочный код, но не имеет категории
                if (!empty($item['markingCode']) && (empty($item['category']))) {
                    return [
                        'success' => false,
                        'message' => "для маркированного товара '{$item['name']}' (позиция " . ($index + 1) . ") не указана категория"
                    ];
                }
            }
        }
        
        return ['success' => true];
    }
} 