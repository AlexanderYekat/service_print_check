package websocket

import (
	"fmt"
	consttypes "service_print_check/consttypes"
	logsmy "service_print_check/packetlog"

	"github.com/gorilla/websocket"
)

// handleWSPrintSlip обрабатывает команду печати слипа через веб-сокет
func (h *Handler) handleWSPrintSlip(conn *websocket.Conn, data map[string]any) {
	// Проверяем наличие текста для печати
	slipText, ok := data["text"].(string)
	if !ok || slipText == "" {
		h.sendWSError(conn, "Ошибка: не указан текст для печати")
		return
	}

	// Получаем экземпляр драйвера
	fptr, err := h.GetDriver()
	if err != nil {
		h.sendWSError(conn, fmt.Sprintf("Ошибка при инициализации драйвера ККТ: %v", err))
		return
	}
	// Печатаем слип
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Печать слипа через веб-сокет")
	err = h.printer.PrintSlip(fptr, slipText)
	if err != nil {
		h.sendWSError(conn, "Ошибка печати слипа: "+err.Error())
		return
	}

	// Отправляем успешный ответ
	h.sendWSResponse(conn, "success", "Слип успешно напечатан", nil)
}
