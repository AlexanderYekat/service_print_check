<?php

/**
 * Форматтер ответов API
 * Обеспечивает единообразное форматирование всех ответов
 */
class ResponseFormatter
{
    /**
     * Форматирование успешного ответа
     */
    public static function success(array $data, array $meta = []): array
    {
        return [
            'success' => true,
            'data' => $data,
            'meta' => array_merge([
                'timestamp' => date('Y-m-d H:i:s'),
                'version' => '2.0.0'
            ], $meta)
        ];
    }

    /**
     * Форматирование ответа с ошибкой
     */
    public static function error(string $message, int $code = 500, array $details = []): array
    {
        $response = [
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $code
            ],
            'meta' => [
                'timestamp' => date('Y-m-d H:i:s'),
                'version' => '2.0.0'
            ]
        ];

        if (!empty($details)) {
            $response['error']['details'] = $details;
        }

        return $response;
    }

    /**
     * Форматирование списка с пагинацией
     */
    public static function paginated(array $items, int $total, int $page = 1, int $perPage = 20): array
    {
        return self::success($items, [
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage)
            ]
        ]);
    }

    /**
     * Форматирование статистики
     */
    public static function stats(array $stats): array
    {
        return self::success($stats, [
            'type' => 'statistics'
        ]);
    }
}