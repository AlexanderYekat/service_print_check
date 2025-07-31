<?php

namespace App\Interface;

use App\Domain\Model\OperationResult;

/**
 * Интерфейс для работы с банковским терминалом
 * 
 * Определяет контракт для всех операций с банковским терминалом:
 * - оплата, возврат, отмена, закрытие смены
 * - получение результата операции в едином формате
 */
interface BankTerminalInterface
{
    /**
     * Выполнить оплату через банковский терминал
     *
     * @param float $amount Сумма платежа
     * @return OperationResult Результат операции со слипом или ошибкой
     */
    public function pay(float $amount): OperationResult;

    /**
     * Выполнить возврат денежных средств
     *
     * @param float $amount Сумма возврата
     * @return OperationResult Результат операции со слипом или ошибкой
     */
    public function refund(float $amount): OperationResult;

    /**
     * Отменить операцию
     *
     * @param float $amount Сумма операции для отмены
     * @return OperationResult Результат отмены
     */
    public function cancel(float $amount): OperationResult;

    /**
     * Закрыть смену на банковском терминале
     *
     * @return OperationResult Результат закрытия смены
     */
    public function closeShift(): OperationResult;
}
