package websocket

import (
	"encoding/json"
	"fmt"
	"net/http"
	"service_print_check/beznal"
	consttypes "service_print_check/consttypes"
	fptr10 "service_print_check/fptr"
	"service_print_check/kktutils"
	"service_print_check/models"
	logsmy "service_print_check/packetlog"
	"strconv"

	"github.com/gorilla/websocket"
	"github.com/mitchellh/mapstructure"
)

// Используем интерфейс IAbstractPrinter из пакета kktutils
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
	comport           *int
	ipaddresskkt      *string
	portkktatol       *int
	ipaddressservrkkt *string
	emulation         *bool
	FptrDriver        consttypes.IFptr10Interface
	printer           kktutils.IAbstractPrinter
	version           string
}

// NewHandler создает новый обработчик веб-сокетов
func NewHandler(
	comport *int,
	ipaddresskkt *string,
	portkktatol *int,
	ipaddressservrkkt *string,
	emulation *bool,
	FptrDriver consttypes.IFptr10Interface,
	version string,
) *Handler {
	h := &Handler{
		comport:           comport,
		ipaddresskkt:      ipaddresskkt,
		portkktatol:       portkktatol,
		ipaddressservrkkt: ipaddressservrkkt,
		emulation:         emulation,
		FptrDriver:        FptrDriver,
		version:           version,
	}
	h.printer = kktutils.NewPrinter(comport, ipaddresskkt, portkktatol, ipaddressservrkkt, emulation)
	return h
}

// GetDriver возвращает драйвер ККТ
func (h *Handler) GetDriver() (consttypes.IFptr10Interface, error) {
	if h.FptrDriver == nil {
		err := h.initializeDriver()
		if err != nil {
			return nil, err
		}
	}
	return h.FptrDriver, nil
}

// HandleWebSocket обрабатывает веб-сокет соединения
func (h *Handler) HandleWebSocket(w http.ResponseWriter, r *http.Request) {
	conn, err := upgrader.Upgrade(w, r, nil)
	if err != nil {
		logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при установке веб-сокет соединения: %v", err)
		return
	}
	defer conn.Close()

	// Отправляем версию программы клиенту при подключении
	h.sendWSResponse(conn, "version", "", map[string]interface{}{
		"version":    h.version,
		"emulation":  *h.emulation,
	})

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

		// В режиме эмуляции возвращаем мок-данные
		if *h.emulation {
			responseType, message, data := GetMockResponse(wsMsg.Command, wsMsg.Data)
			h.sendWSResponse(conn, responseType, message, data)
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
		case "payMany":
			h.handleWSPayMany(conn, wsMsg.Data)
		case "returnMany":
			h.handleWSReturnMany(conn, wsMsg.Data)
		case "closeShiftTerminal":
			h.handleWSCloseShiftTerminal(conn)
		case "printSlip":
			h.handleWSPrintSlip(conn, wsMsg.Data)
		case "printText":
			h.handleWSPrintText(conn, wsMsg.Data)
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
		err := fmt.Errorf("Ошибка при создании декодера: %v", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error())
		return
	}

	if err := decoder.Decode(data); err != nil {
		err := fmt.Errorf("Ошибка при разборе данных чека: %v", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error())
		return
	}

	// Проверяем обязательные поля
	if checkData.Cashier == "" {
		err := fmt.Errorf("Не указано имя кассира")
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error())
		return
	}
	if len(checkData.TableData) == 0 {
		err := fmt.Errorf("Отсутствуют позиции в чеке")
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error())
		return
	}

	// Форматирование и отправка данных чека
	checkJSON := kktutils.FormatCheckJSON(checkData)

	fptr, err := h.GetDriver()
	if err != nil {
		err := fmt.Errorf("Ошибка при инициализации драйвера ККТ: %v", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error())
		return
	}

	// Подключаемся к кассе
	if ok, typepodkluch := kktutils.ConnectWithKassa(fptr, *h.comport, *h.ipaddresskkt, *h.portkktatol, *h.ipaddressservrkkt); !ok {
		err := fmt.Errorf("Ошибка подключения к кассе: %v", typepodkluch)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error())
		return
	}

	defer kktutils.CloseKassa(fptr)

	// Печатаем чек
	result, err := kktutils.SendCommandAndGetAnswerFromKKT(fptr, checkJSON, *h.emulation)
	if err != nil {
		err := fmt.Errorf("Ошибка при печати чека: %v", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error())
		return
	}

	if !kktutils.SuccessCommand(result) {
		err := fmt.Errorf("Ошибка при печати чека: %v", result)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error())
		return
	}

	// Парсим результат и получаем fiscalDocumentNumber
	var resultJSON struct {
		FiscalParams struct {
			FiscalDocumentNumber int `json:"fiscalDocumentNumber"`
		} `json:"fiscalParams"`
	}
	if err := json.Unmarshal([]byte(result), &resultJSON); err != nil {
		if !*h.emulation {
			err := fmt.Errorf("Ошибка при разборе JSON результата: %v", err)
			logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
			h.sendWSError(conn, err.Error())
			return
		} else {
			resultJSON.FiscalParams.FiscalDocumentNumber = 123
		}
	}

	h.sendWSResponse(conn, "success", "Чек успешно напечатан", map[string]interface{}{
		"fiscalDocumentNumber": resultJSON.FiscalParams.FiscalDocumentNumber,
	})
}

func (h *Handler) handleWSCloseShift(conn *websocket.Conn, data map[string]any) {
	cashier, ok := data["cashier"].(string)
	if !ok {
		h.sendWSError(conn, "Не указано имя кассира")
		return
	}

	fptr, err := h.GetDriver()
	if err != nil {
		err := fmt.Errorf("Ошибка при инициализации драйвера ККТ: %v", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error())
		return
	}
	defer kktutils.CloseKassa(fptr)

	// Подключаемся к кассе
	if ok, typepodkluch := kktutils.ConnectWithKassa(fptr, *h.comport, *h.ipaddresskkt, *h.portkktatol, *h.ipaddressservrkkt); !ok {
		err := fmt.Errorf("Ошибка подключения к кассе: %v", typepodkluch)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error())
		return
	}

	// Закрываем смену
	fiscalDocumentNumber, err := kktutils.CloseShift(fptr, cashier, *h.emulation)
	if err != nil {
		err := fmt.Errorf("Ошибка при закрытии смены: %v", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error())
		return
	}

	h.sendWSResponse(conn, "success", "Смена успешно закрыта", map[string]interface{}{
		"fiscalDocumentNumber": fiscalDocumentNumber,
	})
}

func (h *Handler) handleWSXReport(conn *websocket.Conn) {
	fptr, err := h.GetDriver()
	if err != nil {
		h.sendWSError(conn, fmt.Sprintf("Ошибка при инициализации драйвера ККТ: %v", err))
		return
	}
	if err := h.printer.PrintXReport(fptr); err != nil {
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

	fptr, err := h.GetDriver()
	if err != nil {
		h.sendWSError(conn, fmt.Sprintf("Ошибка при инициализации драйвера ККТ: %v", err))
		return
	}
	defer kktutils.CloseKassa(fptr)

	// Подключаемся к кассе
	if ok, typepodkluch := kktutils.ConnectWithKassa(fptr, *h.comport, *h.ipaddresskkt, *h.portkktatol, *h.ipaddressservrkkt); !ok {
		h.sendWSError(conn, fmt.Sprintf("Ошибка подключения к кассе: %v", typepodkluch))
		return
	}

	// Выполняем операцию
	err = kktutils.CashInOut(fptr, cashier, cashSum, cashIn, *h.emulation)
	if err != nil {
		h.sendWSError(conn, fmt.Sprintf("Ошибка при внесении/выплате наличных: %v", err))
		return
	}

	h.sendWSResponse(conn, "success", "Наличные успешно внесены/выплачены", nil)
}

func (h *Handler) handleWSPayMany(conn *websocket.Conn, data map[string]any) {
	var amount int

	// Обработка разных возможных типов данных
	switch v := data["amount"].(type) {
	case int:
		amount = v
	case int64:
		amount = int(v)
	case float64:
		amount = int(v)
	case string:
		var err error
		amount, err = strconv.Atoi(v)
		if err != nil {
			h.sendWSError(conn, fmt.Sprintf("Не удалось преобразовать строку в число: %v", err))
			return
		}
	case map[string]interface{}:
		// Это для отладки - выведем информацию о том, что приходит в данном случае
		err := fmt.Errorf("Получен объект вместо числа: %v", v)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error())
		return
	default:
		err := fmt.Errorf("Неподдерживаемый тип данных для суммы: %T", v)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error())
		return
	}

	if amount == 0 {
		err := fmt.Errorf("Сумма платежа не может быть 0")
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error())
		return
	}

	receipt, err := beznal.PayMoney(amount)
	if err != nil {
		err := fmt.Errorf("Ошибка при оплате по безналу: %v", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error())
		return
	}

	h.sendWSResponse(conn, "success", "Оплата по безналу прошла успешно", map[string]interface{}{
		"slip": receipt,
	})
}

func (h *Handler) handleWSReturnMany(conn *websocket.Conn, data map[string]any) {
	var amount int
	// Обработка разных возможных типов данных
	switch v := data["amount"].(type) {
	case int:
		amount = v
	case int64:
		amount = int(v)
	case float64:
		amount = int(v)
	case string:
		var err error
		amount, err = strconv.Atoi(v)
		if err != nil {
			h.sendWSError(conn, fmt.Sprintf("Не удалось преобразовать строку в число: %v", err))
			return
		}
	case map[string]interface{}:
		// Это для отладки - выведем информацию о том, что приходит в данном случае
		err := fmt.Errorf("Получен объект вместо числа: %v", v)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error())
		return
	default:
		err := fmt.Errorf("Неподдерживаемый тип данных для суммы: %T", v)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error())
		return
	}

	if amount == 0 {
		err := fmt.Errorf("Сумма возврата не может быть 0")
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error())
		return
	}

	receipt, err := beznal.ReturnMoney(amount)
	if err != nil {
		err := fmt.Errorf("Ошибка при возрате на карту по терминалу: %v", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error())
		return
	}

	h.sendWSResponse(conn, "success", "Возврат по карте прошел успешно", map[string]interface{}{
		"slip": receipt,
	})
}

func (h *Handler) handleWSCloseShiftTerminal(conn *websocket.Conn) {
	// Закрываем банковскую смену
	receipt, err := beznal.CloseShiftTerminal()
	if err != nil {
		err := fmt.Errorf("Ошибка при закрытии смены по терминалу: %v", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error())
		return
	}

	h.sendWSResponse(conn, "success", "Банковская смена успешно закрыта", map[string]interface{}{
		"slip": receipt,
	})
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
		err := fmt.Errorf("Ошибка при отправке ответа: %v", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
	}
}

func (h *Handler) initializeDriver() error {
	// Реализация инициализации драйвера ККТ
	fptr, err := fptr10.NewSafe()
	if err != nil {
		err := fmt.Errorf("ошибка инициализации драйвера ККТ: %v", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		return err
	}
	h.FptrDriver = fptr
	return nil
}
