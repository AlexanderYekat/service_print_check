package websocket

import (
	"encoding/json"
	"fmt"
	"net/http"
	consttypes "service_print_check/consttypes"
	"service_print_check/equipment"
	fptr10 "service_print_check/fptr"
	"service_print_check/kktutils"
	"service_print_check/models"
	logsmy "service_print_check/packetlog"
	"strconv"
	"time"

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
	ID      string         `json:"id,omitempty"` // Идентификатор сообщения
}

// WSResponse представляет структуру исходящего веб-сокет сообщения
type WSResponse struct {
	Type    string      `json:"type"`
	Message string      `json:"message"`
	Data    interface{} `json:"data,omitempty"`
	ID      string      `json:"id,omitempty"`   // Идентификатор сообщения (совпадает с ID запроса)
	Time    int64       `json:"time,omitempty"` // Время отправки ответа
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

	// Устанавливаем таймаут для ответов (чтобы избежать зависания сокета)
	conn.SetReadDeadline(time.Now().Add(60 * time.Second))
	conn.SetWriteDeadline(time.Now().Add(10 * time.Second))

	// Счетчик для генерации ID сообщений, если они не указаны клиентом
	var messageCounter int64 = 0

	// Отправляем версию программы клиенту при подключении
	h.sendWSResponse(conn, "version", "", map[string]interface{}{
		"version":   h.version,
		"emulation": *h.emulation,
	}, "")

	for {
		// Обновляем таймаут чтения при каждой итерации
		conn.SetReadDeadline(time.Now().Add(60 * time.Second))

		_, message, err := conn.ReadMessage()
		if err != nil {
			if websocket.IsUnexpectedCloseError(err, websocket.CloseGoingAway, websocket.CloseAbnormalClosure) {
				logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при чтении веб-сокет сообщения: %v", err)
			}
			break
		}

		var wsMsg WSMessage
		if err := json.Unmarshal(message, &wsMsg); err != nil {
			h.sendWSError(conn, "Ошибка при разборе JSON сообщения", "")
			continue
		}

		// Если ID не указан клиентом, генерируем его
		if wsMsg.ID == "" {
			messageCounter++
			wsMsg.ID = fmt.Sprintf("msg-%d", messageCounter)
		}

		// Логируем полученную команду
		logsmy.Logsmap[consttypes.LOGINFO].Printf("WS запрос [%s]: команда %s", wsMsg.ID, wsMsg.Command)

		// В режиме эмуляции возвращаем мок-данные
		if *h.emulation {
			responseType, message, data := GetMockResponse(wsMsg.Command, wsMsg.Data)
			h.sendWSResponse(conn, responseType, message, data, wsMsg.ID)
			continue
		}

		// Устанавливаем таймаут записи перед отправкой ответа
		conn.SetWriteDeadline(time.Now().Add(10 * time.Second))

		// Для долгих операций сначала отправляем статус "processing",
		// а затем уже результат выполнения операции
		switch wsMsg.Command {
		case "printCheck":
			// Отправляем статус "processing" перед выполнением операции
			h.sendWSProcessing(conn, fmt.Sprintf("Печать чека в процессе... [%s]", wsMsg.ID), wsMsg.ID)
			h.handleWSPrintCheck(conn, wsMsg.Data, wsMsg.ID)
		case "closeShift":
			h.sendWSProcessing(conn, fmt.Sprintf("Закрытие смены в процессе... [%s]", wsMsg.ID), wsMsg.ID)
			h.handleWSCloseShift(conn, wsMsg.Data, wsMsg.ID)
		case "xReport":
			h.sendWSProcessing(conn, fmt.Sprintf("Печать X-отчета в процессе... [%s]", wsMsg.ID), wsMsg.ID)
			h.handleWSXReport(conn, wsMsg.ID)
		case "cashIn":
			h.sendWSProcessing(conn, fmt.Sprintf("Внесение наличных в процессе... [%s]", wsMsg.ID), wsMsg.ID)
			h.handleWSCashInOut(conn, wsMsg.Data, true, wsMsg.ID)
		case "cashOut":
			h.sendWSProcessing(conn, fmt.Sprintf("Выдача наличных в процессе... [%s]", wsMsg.ID), wsMsg.ID)
			h.handleWSCashInOut(conn, wsMsg.Data, false, wsMsg.ID)
		case "payMany":
			h.sendWSProcessing(conn, fmt.Sprintf("Оплата по терминалу в процессе... [%s]", wsMsg.ID), wsMsg.ID)
			h.handleWSPayMany(conn, wsMsg.Data, wsMsg.ID)
		case "returnMany":
			h.sendWSProcessing(conn, fmt.Sprintf("Возврат по терминалу в процессе... [%s]", wsMsg.ID), wsMsg.ID)
			h.handleWSReturnMany(conn, wsMsg.Data, wsMsg.ID)
		case "closeShiftTerminal":
			h.sendWSProcessing(conn, fmt.Sprintf("Закрытие смены терминала в процессе... [%s]", wsMsg.ID), wsMsg.ID)
			h.handleWSCloseShiftTerminal(conn, wsMsg.ID)
		case "printSlip":
			h.sendWSProcessing(conn, fmt.Sprintf("Печать слипа в процессе... [%s]", wsMsg.ID), wsMsg.ID)
			h.handleWSPrintSlip(conn, wsMsg.Data, wsMsg.ID)
		case "printText":
			h.sendWSProcessing(conn, fmt.Sprintf("Печать текста в процессе... [%s]", wsMsg.ID), wsMsg.ID)
			h.handleWSPrintText(conn, wsMsg.Data, wsMsg.ID)
		case "getWeight":
			h.sendWSProcessing(conn, fmt.Sprintf("Получение веса в процессе... [%s]", wsMsg.ID), wsMsg.ID)
			h.handleWSGetWeight(conn, wsMsg.Data, wsMsg.ID)
		case "ping":
			// Специальная команда для проверки соединения
			h.sendWSResponse(conn, "pong", "Соединение активно", nil, wsMsg.ID)
		default:
			h.sendWSError(conn, fmt.Sprintf("Неизвестная команда: %s", wsMsg.Command), wsMsg.ID)
		}

		// Небольшая пауза между обработками сообщений, чтобы избежать конфликтов
		time.Sleep(100 * time.Millisecond)
	}
}

func (h *Handler) handleWSPrintCheck(conn *websocket.Conn, data map[string]any, messageID string) {
	var checkData models.CheckData
	decoder, err := mapstructure.NewDecoder(&mapstructure.DecoderConfig{
		WeaklyTypedInput: true,
		Result:           &checkData,
	})
	if err != nil {
		err := fmt.Errorf("ошибка при создании декодера: %v", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error(), messageID)
		return
	}

	if err := decoder.Decode(data); err != nil {
		err := fmt.Errorf("ошибка при разборе данных чека: %v", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error(), messageID)
		return
	}

	// Проверяем обязательные поля
	if checkData.Cashier == "" {
		err := fmt.Errorf("не указано имя кассира")
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error(), messageID)
		return
	}
	if len(checkData.TableData) == 0 {
		err := fmt.Errorf("отсутствуют позиции в чеке")
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error(), messageID)
		return
	}

	// Форматирование и отправка данных чека
	checkJSON := kktutils.FormatCheckJSON(checkData)

	fptr, err := h.GetDriver()
	if err != nil {
		err := fmt.Errorf("ошибка при инициализации драйвера ККТ: %v", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error(), messageID)
		return
	}

	// Подключаемся к кассе
	if ok, typepodkluch := kktutils.ConnectWithKassa(fptr, *h.comport, *h.ipaddresskkt, *h.portkktatol, *h.ipaddressservrkkt); !ok {
		err := fmt.Errorf("ошибка подключения к кассе: %v", typepodkluch)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error(), messageID)
		return
	}

	defer kktutils.CloseKassa(fptr)

	// Печатаем чек
	result, err := kktutils.SendCommandAndGetAnswerFromKKT(fptr, checkJSON, *h.emulation)
	if err != nil {
		err := fmt.Errorf("ошибка при печати чека: %v", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error(), messageID)
		return
	}

	if !kktutils.SuccessCommand(result) {
		err := fmt.Errorf("ошибка при печати чека: %v", result)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error(), messageID)
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
			err := fmt.Errorf("ошибка при разборе JSON результата: %v", err)
			logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
			h.sendWSError(conn, err.Error(), messageID)
			return
		} else {
			resultJSON.FiscalParams.FiscalDocumentNumber = 123
		}
	}

	h.sendWSResponse(conn, "success", "Чек успешно напечатан", map[string]interface{}{
		"fiscalDocumentNumber": resultJSON.FiscalParams.FiscalDocumentNumber,
	}, messageID)
}

func (h *Handler) handleWSCloseShift(conn *websocket.Conn, data map[string]any, messageID string) {
	cashier, ok := data["cashier"].(string)
	if !ok {
		h.sendWSError(conn, "не указано имя кассира", messageID)
		return
	}

	fptr, err := h.GetDriver()
	if err != nil {
		err := fmt.Errorf("ошибка при инициализации драйвера ККТ: %v", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error(), messageID)
		return
	}
	defer kktutils.CloseKassa(fptr)

	// Подключаемся к кассе
	if ok, typepodkluch := kktutils.ConnectWithKassa(fptr, *h.comport, *h.ipaddresskkt, *h.portkktatol, *h.ipaddressservrkkt); !ok {
		err := fmt.Errorf("ошибка подключения к кассе: %v", typepodkluch)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error(), messageID)
		return
	}

	// Закрываем смену
	fiscalDocumentNumber, err := kktutils.CloseShift(fptr, cashier, *h.emulation)
	if err != nil {
		err := fmt.Errorf("ошибка при закрытии смены: %v", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error(), messageID)
		return
	}

	h.sendWSResponse(conn, "success", "Смена успешно закрыта", map[string]interface{}{
		"fiscalDocumentNumber": fiscalDocumentNumber,
	}, messageID)
}

func (h *Handler) handleWSXReport(conn *websocket.Conn, messageID string) {
	// Получаем драйвер ККТ
	fptr, err := h.GetDriver()
	if err != nil {
		h.sendWSError(conn, fmt.Sprintf("ошибка при инициализации драйвера ККТ: %v", err), messageID)
		return
	}

	// Проверяем подключение к ККТ перед выполнением операции
	if ok, typepodkluch := kktutils.ConnectWithKassa(fptr, *h.comport, *h.ipaddresskkt, *h.portkktatol, *h.ipaddressservrkkt); !ok {
		err := fmt.Errorf("ошибка подключения к кассе при печати X-отчета: %v", typepodkluch)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error(), messageID)
		return
	}
	defer kktutils.CloseKassa(fptr)

	// Печатаем X-отчет
	if err := h.printer.PrintXReport(fptr); err != nil {
		h.sendWSError(conn, fmt.Sprintf("ошибка при печати X-отчета: %v", err), messageID)
		return
	}

	// Логируем успешное выполнение
	logsmy.Logsmap[consttypes.LOGINFO].Printf("WS ответ [%s]: X-отчет успешно напечатан", messageID)

	h.sendWSResponse(conn, "success", "X-отчет успешно напечатан", nil, messageID)
}

func (h *Handler) handleWSCashInOut(conn *websocket.Conn, data map[string]any, cashIn bool, messageID string) {
	cashier, ok := data["cashier"].(string)
	if !ok || cashier == "" {
		h.sendWSError(conn, "не указано имя кассира", messageID)
		return
	}

	cashSumInterface, ok := data["cashSum"]
	if !ok {
		h.sendWSError(conn, "не указана сумма внесения", messageID)
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
			h.sendWSError(conn, fmt.Sprintf("ошибка при преобразовании суммы: %v", err), messageID)
			return
		}
	default:
		h.sendWSError(conn, "ошибка: некорректный формат суммы", messageID)
		return
	}

	fptr, err := h.GetDriver()
	if err != nil {
		h.sendWSError(conn, fmt.Sprintf("ошибка при инициализации драйвера ККТ: %v", err), messageID)
		return
	}
	defer kktutils.CloseKassa(fptr)

	// Подключаемся к кассе
	if ok, typepodkluch := kktutils.ConnectWithKassa(fptr, *h.comport, *h.ipaddresskkt, *h.portkktatol, *h.ipaddressservrkkt); !ok {
		h.sendWSError(conn, fmt.Sprintf("ошибка подключения к кассе: %v", typepodkluch), messageID)
		return
	}

	// Выполняем операцию
	err = kktutils.CashInOut(fptr, cashier, cashSum, cashIn, *h.emulation)
	if err != nil {
		h.sendWSError(conn, fmt.Sprintf("ошибка при внесении/выплате наличных: %v", err), messageID)
		return
	}

	h.sendWSResponse(conn, "success", "Наличные успешно внесены/выплачены", nil, messageID)
}

func (h *Handler) handleWSPayMany(conn *websocket.Conn, data map[string]any, messageID string) {
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
			h.sendWSError(conn, fmt.Sprintf("ошибка: не удалось преобразовать строку в число: %v", err), messageID)
			return
		}
	case map[string]interface{}:
		// Это для отладки - выведем информацию о том, что приходит в данном случае
		err := fmt.Errorf("ошибка: получен объект вместо числа: %v", v)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error(), messageID)
		return
	default:
		err := fmt.Errorf("ошибка: неподдерживаемый тип данных для суммы: %T", v)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error(), messageID)
		return
	}

	if amount == 0 {
		err := fmt.Errorf("ошибка: сумма платежа не может быть 0")
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error(), messageID)
		return
	}

	receipt, err := equipment.PayMoney(amount)
	if err != nil {
		err := fmt.Errorf("ошибка: ошибка при оплате по безналу: %v", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error(), messageID)
		return
	}

	h.sendWSResponse(conn, "success", "Оплата по безналу прошла успешно", map[string]interface{}{
		"slip": receipt,
	}, messageID)
}

func (h *Handler) handleWSReturnMany(conn *websocket.Conn, data map[string]any, messageID string) {
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
			h.sendWSError(conn, fmt.Sprintf("ошибка: не удалось преобразовать строку в число: %v", err), messageID)
			return
		}
	case map[string]interface{}:
		// Это для отладки - выведем информацию о том, что приходит в данном случае
		err := fmt.Errorf("ошибка: получен объект вместо числа: %v", v)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error(), messageID)
		return
	default:
		err := fmt.Errorf("ошибка: неподдерживаемый тип данных для суммы: %T", v)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error(), messageID)
		return
	}

	if amount == 0 {
		err := fmt.Errorf("ошибка: сумма возврата не может быть 0")
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error(), messageID)
		return
	}

	receipt, err := equipment.ReturnMoney(amount)
	if err != nil {
		err := fmt.Errorf("ошибка: ошибка при возврате на карту по терминалу: %v", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error(), messageID)
		return
	}

	h.sendWSResponse(conn, "success", "Возврат по карте прошел успешно", map[string]interface{}{
		"slip": receipt,
	}, messageID)
}

func (h *Handler) handleWSCloseShiftTerminal(conn *websocket.Conn, messageID string) {
	// Закрываем банковскую смену
	receipt, err := equipment.CloseShiftTerminal()
	if err != nil {
		err := fmt.Errorf("ошибка: ошибка при закрытии смены по терминалу: %v", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		h.sendWSError(conn, err.Error(), messageID)
		return
	}

	h.sendWSResponse(conn, "success", "Банковская смена успешно закрыта", map[string]interface{}{
		"slip": receipt,
	}, messageID)
}

func (h *Handler) handleWSGetWeight(conn *websocket.Conn, data map[string]interface{}, messageID string) {
	//weight2 := 500
	comPortInt := 1
	comPort, ok := data["com"].(string)
	if ok {
		var err error
		comPortInt, err = strconv.Atoi(comPort)
		if err != nil {
			h.sendWSError(conn, fmt.Sprintf("ошибка: не удалось преобразовать COM-порт в число: %v", err), messageID)
			return
		}
	}

	weight, err := equipment.GetWeight(comPortInt)
	if err != nil {
		h.sendWSError(conn, fmt.Sprintf("ошибка: ошибка при получении веса: %v", err), messageID)
		return
	}

	h.sendWSResponse(conn, "success", "Вес успешно получен", map[string]interface{}{
		"weight": weight,
	}, messageID)
}

func (h *Handler) sendWSError(conn *websocket.Conn, message string, messageID string) {
	// Логирование ошибки перед отправкой клиенту
	logsmy.Logsmap[consttypes.LOGERROR].Printf("WS ошибка [%s]: %s", messageID, message)

	response := models.WSResponse{
		Type:    "error",
		Message: message,
		ID:      messageID,
		Time:    time.Now().UnixNano() / int64(time.Millisecond),
	}
	if err := conn.WriteJSON(response); err != nil {
		logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при отправке сообщения об ошибке [%s]: %v", messageID, err)
	}
}

func (h *Handler) sendWSResponse(conn *websocket.Conn, responseType, message string, data interface{}, messageID string) {
	// Логируем отправку ответа
	logsmy.Logsmap[consttypes.LOGINFO].Printf("WS ответ [%s]: тип %s", messageID, responseType)

	response := models.WSResponse{
		Type:    responseType,
		Message: message,
		Data:    data,
		ID:      messageID,
		Time:    time.Now().UnixNano() / int64(time.Millisecond),
	}
	if err := conn.WriteJSON(response); err != nil {
		err := fmt.Errorf("ошибка: ошибка при отправке ответа [%s]: %v", messageID, err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
	}
}

func (h *Handler) sendWSProcessing(conn *websocket.Conn, message string, messageID string) {
	// Логируем начало обработки
	logsmy.Logsmap[consttypes.LOGINFO].Printf("WS обработка [%s]: %s", messageID, message)

	response := models.WSResponse{
		Type:    "processing",
		Message: message,
		ID:      messageID,
		Time:    time.Now().UnixNano() / int64(time.Millisecond),
	}
	if err := conn.WriteJSON(response); err != nil {
		logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при отправке статуса обработки [%s]: %v", messageID, err)
	}
}

func (h *Handler) initializeDriver() error {
	// Реализация инициализации драйвера ККТ
	fptr, err := fptr10.NewSafe()
	if err != nil {
		err := fmt.Errorf("ошибка: ошибка инициализации драйвера ККТ: %v", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(err.Error())
		return err
	}
	h.FptrDriver = fptr
	return nil
}
