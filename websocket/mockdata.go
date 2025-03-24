package websocket

// MockData содержит мок-данные для использования в режиме эмуляции
type MockData struct {
	// Данные для команд
}

// GetMockResponse возвращает мок-ответ для указанной команды
func GetMockResponse(command string, data map[string]any) (string, string, interface{}) {
	switch command {
	case "printCheck":
		return "success", "Чек успешно напечатан (эмуляция)", map[string]interface{}{
			"fiscalDocumentNumber": "12345",
		}
	case "closeShift":
		return "success", "Смена успешно закрыта (эмуляция)", map[string]interface{}{
			"fiscalDocumentNumber": "67890",
		}
	case "xReport":
		return "success", "X-отчет успешно напечатан (эмуляция)", nil
	case "cashIn":
		// В реальном обработчике возвращается только сообщение об успехе, без данных
		return "success", "Наличные успешно внесены (эмуляция)", nil
	case "cashOut":
		// В реальном обработчике возвращается только сообщение об успехе, без данных
		return "success", "Наличные успешно выплачены (эмуляция)", nil
	case "payMany":
		return "success", "Оплата по безналу успешно выполнена (эмуляция)", map[string]interface{}{
			"slip": "Мок-слип оплаты по банковской карте\nСумма: 1000.00 руб.\nКарта: XXXX XXXX XXXX 0000\nОдобрено",
		}
	case "returnMany":
		return "success", "Возврат по безналу успешно выполнен (эмуляция)", map[string]interface{}{
			"slip": "Мок-слип возврата по банковской карте\nСумма: 500.00 руб.\nКарта: XXXX XXXX XXXX 0000\nВозврат выполнен",
		}
	case "closeShiftTerminal":
		return "success", "Банковская смена успешно закрыта (эмуляция)", map[string]interface{}{
			"slip": "Мок-слип закрытия банковской смены\nТерминал: 12345\nИтого: 10000.00 руб.",
		}
	case "printSlip":
		return "success", "Слип успешно напечатан (эмуляция)", nil
	case "printText":
		return "success", "Текст успешно напечатан (эмуляция)", nil
	case "getWeight":
		return "success", "Вес успешно получен (эмуляция)", map[string]interface{}{
			"weight": 1000,
		}
	default:
		return "error", "Неизвестная команда в режиме эмуляции", nil
	}
}
