package websocket

import (
	"fmt"

	"github.com/gorilla/websocket"
)

// handleWSPrintSlip обрабатывает команду печати слипа через веб-сокет
func (h *Handler) handleWSPrintSlip(conn *websocket.Conn, data map[string]any, messageID string) {
	// Проверяем наличие текста для печати
	slipText, ok := data["text"].(string)
	if !ok || slipText == "" {
		h.sendWSError(conn, "Ошибка: не указан текст для печати", messageID)
		return
	}

	// Получаем экземпляр драйвера
	fptr, err := h.GetDriver()
	if err != nil {
		h.sendWSError(conn, fmt.Sprintf("Ошибка при инициализации драйвера ККТ: %v", err), messageID)
		return
	}

	// Проверяем наличие параметра alignment
	alignment, _ := data["alignment"].(string)
	if alignment == "" {
		alignment = "left" // По умолчанию используем выравнивание по левому краю
	}

	// Печатаем слип
	if err := h.printer.PrintSlip(fptr, slipText); err != nil {
		h.sendWSError(conn, fmt.Sprintf("Ошибка при печати слипа: %v", err), messageID)
		return
	}

	// Возвращаем успешный результат
	h.sendWSResponse(conn, "success", "Слип успешно напечатан", nil, messageID)
}
