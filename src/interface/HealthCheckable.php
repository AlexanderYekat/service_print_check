<?php

namespace App\Interface;

/**
 * Интерфейс для проверки состояния инфраструктурных компонентов
 * 
 * Все адаптеры (банк, ККТ, Честный Знак, весы) должны реализовывать этот интерфейс
 * для поддержки мониторинга состояния системы
 */
interface HealthCheckable
{
    /**
     * Проверяет состояние компонента
     * 
     * @return array Массив с информацией о состоянии:
     *               - 'status' => 'ok'|'warning'|'error'
     *               - 'message' => string с описанием состояния
     *               - 'details' => array с дополнительной информацией (опционально)
     *               - 'response_time_ms' => int время отклика в миллисекундах (опционально)
     */
    public function checkHealth(): array;

    /**
     * Возвращает имя компонента для отображения в health-check
     * 
     * @return string Имя компонента (например: "bank_terminal", "kkt_printer", "honest_sign_api")
     */
    public function getComponentName(): string;
}