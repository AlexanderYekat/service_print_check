package websocket

import (
	"encoding/json"
	"fmt"
	"net/http"
	consttypes "service_print_check/consttypes"
	"service_print_check/models"
	logsmy "service_print_check/packetlog"

	"strconv"

	"github.com/gorilla/websocket"
	"github.com/mitchellh/mapstructure"
)

// TAbstractPrinter представляет интерфейс для печати отчетов
type TAbstractPrinter interface {
	PrintXReport(fptr consttypes.IFptr10Interface) error
}

var upgrader = websocket.Upgrader{
	ReadBufferSize:  1024,
	WriteBufferSize: 1024,
	CheckOrigin: func(r *http.Request) bool {
		return true // В продакшене нужно настроить более строгую проверку
	},
}

// WSMessage представляет структуру входящего веб-сокет сообщения
type WSMessage struct {
	Command string         `json:"command"`
	Data    map[string]any `json:"data,omitempty"`
}

// WSResponse представляет структуру исходящего веб-сокет сообщения
type WSResponse struct {
	Type    string      `json:"type"`
	Message string      `json:"message"`
	Data    interface{} `json:"data,omitempty"`
}

// Handler представляет обработчик веб-сокетов
type Handler struct {
	comport                        *int
	ipaddresskkt                   *string
	portkktatol                    *int
	ipaddressservrkkt              *string
	emulation                      *bool
	formatCheckJSON                func(checkData models.CheckData) string
	closeShift                     func(cashier string) error
	connectWithKassa               func(fptr consttypes.IFptr10Interface, comportint int, ipaddresskktper string, portkktper int, ipaddresssrvkktper string) (bool, string)
	sendComandeAndGetAnswerFromKKT func(fptr consttypes.IFptr10Interface, comJson string) (string, error)
	successCommand                 func(resulJson string) bool
	glFptrDriver                   consttypes.IFptr10Interface
	printer                        TAbstractPrinter
	cashInOut                      func(cashier string, cashSum float64, cashIn bool) error
}

// NewHandler создает новый обработчик веб-сокетов
func NewHandler(
	comport *int,
	ipaddresskkt *string,
	portkktatol *int,
	ipaddressservrkkt *string,
	emulation *bool,
	formatCheckJSON func(checkData models.CheckData) string,
	closeShift func(cashier string) error,
	connectWithKassa func(fptr consttypes.IFptr10Interface, comportint int, ipaddresskktper string, portkktper int, ipaddresssrvkktper string) (bool, string),
	sendComandeAndGetAnswerFromKKT func(fptr consttypes.IFptr10Interface, comJson string) (string, error),
	successCommand func(resulJson string) bool,
	glFptrDriver consttypes.IFptr10Interface,
	cashInOut func(cashier string, cashSum float64, cashIn bool) error,
) *Handler {
	h := &Handler{
		comport:                        comport,
		ipaddresskkt:                   ipaddresskkt,
		portkktatol:                    portkktatol,
		ipaddressservrkkt:              ipaddressservrkkt,
		emulation:                      emulation,
		formatCheckJSON:                formatCheckJSON,
		closeShift:                     closeShift,
		connectWithKassa:               connectWithKassa,
		sendComandeAndGetAnswerFromKKT: sendComandeAndGetAnswerFromKKT,
		successCommand:                 successCommand,
		glFptrDriver:                   glFptrDriver,
		cashInOut:                      cashInOut,
	}
	h.printer = &PrinterImpl{handler: h}
	return h
}

// HandleWebSocket обрабатывает веб-сокет соединения
func (h *Handler) HandleWebSocket(w http.ResponseWriter, r *http.Request) {
	conn, err := upgrader.Upgrade(w, r, nil)
	if err != nil {
		logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при установке веб-сокет соединения: %v", err)
		return
	}
	defer conn.Close()

	for {
		_, message, err := conn.ReadMessage()
		if err != nil {
			if websocket.IsUnexpectedCloseError(err, websocket.CloseGoingAway, websocket.CloseAbnormalClosure) {
				logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при чтении веб-сокет сообщения: %v", err)
			}
			break
		}

		var wsMsg models.WSMessage
		if err := json.Unmarshal(message, &wsMsg); err != nil {
			h.sendWSError(conn, "Ошибка при разборе JSON сообщения")
			continue
		}

		switch wsMsg.Command {
		case "printCheck":
			h.handleWSPrintCheck(conn, wsMsg.Data)
		case "closeShift":
			h.handleWSCloseShift(conn, wsMsg.Data)
		case "xReport":
			h.handleWSXReport(conn)
		case "cashIn":
			h.handleWSCashInOut(conn, wsMsg.Data, true)
		case "cashOut":
			h.handleWSCashInOut(conn, wsMsg.Data, false)
		default:
			h.sendWSError(conn, fmt.Sprintf("Неизвестная команда: %s", wsMsg.Command))
		}
	}
}

func (h *Handler) handleWSPrintCheck(conn *websocket.Conn, data map[string]any) {
	var checkData models.CheckData
	decoder, err := mapstructure.NewDecoder(&mapstructure.DecoderConfig{
		WeaklyTypedInput: true,
		Result:           &checkData,
	})
	if err != nil {
		h.sendWSError(conn, fmt.Sprintf("Ошибка при создании декодера: %v", err))
		return
	}

	if err := decoder.Decode(data); err != nil {
		h.sendWSError(conn, fmt.Sprintf("Ошибка при разборе данных чека: %v", err))
		return
	}

	// Проверяем обязательные поля
	if checkData.Cashier == "" {
		h.sendWSError(conn, "Не указано имя кассира")
		return
	}
	if len(checkData.TableData) == 0 {
		h.sendWSError(conn, "Отсутствуют позиции в чеке")
		return
	}

	// Форматирование и отправка данных чека
	checkJSON := h.formatCheckJSON(checkData)

	// Подключение к кассе
	if ok, typepodkluch := h.connectWithKassa(h.glFptrDriver, *h.comport, *h.ipaddresskkt, *h.portkktatol, *h.ipaddressservrkkt); !ok {
		h.sendWSError(conn, fmt.Sprintf("Ошибка подключения к кассе: %v", typepodkluch))
		return
	}
	defer h.glFptrDriver.Close()

	result, err := h.sendComandeAndGetAnswerFromKKT(h.glFptrDriver, checkJSON)
	if err != nil {
		h.sendWSError(conn, fmt.Sprintf("Ошибка при печати чека: %v", err))
		return
	}

	if !h.successCommand(result) {
		h.sendWSError(conn, fmt.Sprintf("Ошибка при печати чека: %v", result))
		return
	}

	// Парсим результат и получаем fiscalDocumentNumber
	var resultJSON struct {
		FiscalDocumentNumber int `json:"fiscalDocumentNumber"`
	}
	if err := json.Unmarshal([]byte(result), &resultJSON); err != nil {
		if !*h.emulation {
			h.sendWSError(conn, fmt.Sprintf("Ошибка при разборе JSON результата: %v", err))
			return
		} else {
			resultJSON.FiscalDocumentNumber = 123
		}
	}

	h.sendWSResponse(conn, "success", "Чек успешно напечатан", map[string]interface{}{
		"fiscalDocumentNumber": resultJSON.FiscalDocumentNumber,
	})
}

func (h *Handler) handleWSCloseShift(conn *websocket.Conn, data map[string]any) {
	cashier, ok := data["cashier"].(string)
	if !ok {
		h.sendWSError(conn, "Не указано имя кассира")
		return
	}

	if err := h.closeShift(cashier); err != nil {
		h.sendWSError(conn, fmt.Sprintf("Ошибка при закрытии смены: %v", err))
		return
	}

	h.sendWSResponse(conn, "success", "Смена успешно закрыта", nil)
}

func (h *Handler) handleWSXReport(conn *websocket.Conn) {
	if err := h.printer.PrintXReport(h.glFptrDriver); err != nil {
		h.sendWSError(conn, fmt.Sprintf("Ошибка при печати X-отчета: %v", err))
		return
	}

	h.sendWSResponse(conn, "success", "X-отчет успешно напечатан", nil)
}

func (h *Handler) handleWSCashInOut(conn *websocket.Conn, data map[string]any, cashIn bool) {
	cashier, ok := data["cashier"].(string)
	if !ok || cashier == "" {
		h.sendWSError(conn, "Не указано имя кассира")
		return
	}

	cashSumInterface, ok := data["cashSum"]
	if !ok {
		h.sendWSError(conn, "Не указана сумма внесения")
		return
	}

	var cashSum float64
	switch v := cashSumInterface.(type) {
	case float64:
		cashSum = v
	case int:
		cashSum = float64(v)
	case string:
		var err error
		cashSum, err = strconv.ParseFloat(v, 64)
		if err != nil {
			h.sendWSError(conn, fmt.Sprintf("Ошибка при преобразовании суммы: %v", err))
			return
		}
	default:
		h.sendWSError(conn, "Некорректный формат суммы")
		return
	}

	err := h.cashInOut(cashier, cashSum, cashIn)
	if err != nil {
		h.sendWSError(conn, fmt.Sprintf("Ошибка при внесении/выплате наличных: %v", err))
		return
	}

	h.sendWSResponse(conn, "success", "Наличные успешно внесены/выплачены", nil)
}

func (h *Handler) sendWSError(conn *websocket.Conn, message string) {
	response := models.WSResponse{
		Type:    "error",
		Message: message,
	}
	if err := conn.WriteJSON(response); err != nil {
		logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при отправке сообщения об ошибке: %v", err)
	}
}

func (h *Handler) sendWSResponse(conn *websocket.Conn, responseType, message string, data interface{}) {
	response := models.WSResponse{
		Type:    responseType,
		Message: message,
		Data:    data,
	}
	if err := conn.WriteJSON(response); err != nil {
		logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при отправке ответа: %v", err)
	}
}
