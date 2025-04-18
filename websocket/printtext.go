package websocket

import (
	"fmt"
	consttypes "service_print_check/consttypes"
	logsmy "service_print_check/packetlog"

	"github.com/gorilla/websocket"
)

// handleWSPrintText обрабатывает команду прямой печати текста через веб-сокет
func (h *Handler) handleWSPrintText(conn *websocket.Conn, data map[string]any, messageID string) {
	// Проверяем наличие текста для печати
	text, ok := data["text"].(string)
	if !ok || text == "" {
		h.sendWSError(conn, "Ошибка: не указан текст для печати", messageID)
		return
	}

	// Получаем экземпляр драйвера
	fptr, err := h.GetDriver()
	if err != nil {
		h.sendWSError(conn, fmt.Sprintf("Ошибка при инициализации драйвера ККТ: %v", err), messageID)
		return
	}
	// Печатаем текст
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Прямая печать текста через веб-сокет")
	err = h.printer.PrintText(fptr, text)
	if err != nil {
		h.sendWSError(conn, "Ошибка печати текста: "+err.Error(), messageID)
		return
	}

	// Отправляем успешный ответ
	h.sendWSResponse(conn, "success", "Текст успешно напечатан", nil, messageID)
}
