package handlers

import (
	"encoding/json"
	"fmt"
	"net/http"
	"service_print_check/consttypes"
	fptr10 "service_print_check/fptr"
	"service_print_check/kktutils"
	"service_print_check/models"
	"time"
)

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

func (h *Handler) HandlePrintCheck(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "Метод не поддерживается", http.StatusMethodNotAllowed)
		return
	}

	var checkData models.CheckData
	if err := json.NewDecoder(r.Body).Decode(&checkData); err != nil {
		http.Error(w, "Ошибка разбора JSON", http.StatusBadRequest)
		return
	}

	if checkData.Cashier == "" {
		err := fmt.Errorf("не указано имя кассира")
		fmt.Println(err.Error())
		http.Error(w, "не указано имя кассира", http.StatusBadRequest)
		return
	}

	fmt.Println("начали выполнение команды печати чека")
	checkJSON := kktutils.FormatCheckJSON(checkData)
	fptr, err := h.GetDriver()
	if err != nil {
		err := fmt.Errorf("ошибка при инициализации драйвера ККТ: %v", err)
		fmt.Println(err.Error())
		h.sendHandleError(w, err.Error(), "")
		return
	}

	// Подключаемся к кассе
	if ok, typepodkluch := kktutils.ConnectWithKassa(fptr, *h.comport, *h.ipaddresskkt, *h.portkktatol, *h.ipaddressservrkkt); !ok {
		err := fmt.Errorf("ошибка подключения к кассе: %v", typepodkluch)
		fmt.Println(err.Error())
		h.sendHandleError(w, err.Error(), "")
		return
	}

	defer kktutils.CloseKassa(fptr)

	// Печатаем чек
	result, err := kktutils.SendCommandAndGetAnswerFromKKT(fptr, checkJSON, *h.emulation)
	if err != nil {
		err := fmt.Errorf("ошибка при печати чека: %v", err)
		fmt.Println(err.Error())
		h.sendHandleError(w, err.Error(), "")
		return
	}

	if !kktutils.SuccessCommand(result) {
		err := fmt.Errorf("ошибка при печати чека: %v", result)
		fmt.Println(err.Error())
		h.sendHandleError(w, err.Error(), "")
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
			fmt.Println(err.Error())
			h.sendHandleError(w, err.Error(), "")
			return
		} else {
			resultJSON.FiscalParams.FiscalDocumentNumber = 123
		}
	}

	h.sendHandlerResponse(w, "success", "Чек успешно напечатан", map[string]interface{}{
		"fiscalDocumentNumber": resultJSON.FiscalParams.FiscalDocumentNumber,
	}, "")

	if err != nil {
		fmt.Println("Ошибка при печати чека:", err)
		http.Error(w, fmt.Sprintf("Ошибка печати чека: %v", err), http.StatusInternalServerError)
		return
	}

	fmt.Println("выполнили команду печати чека")
}

func (h *Handler) HandleCloseShift(w http.ResponseWriter, r *http.Request) {
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

func (h *Handler) initializeDriver() error {
	// Реализация инициализации драйвера ККТ
	fptr, err := fptr10.NewSafe()
	if err != nil {
		err := fmt.Errorf("ошибка: ошибка инициализации драйвера ККТ: %v", err)
		fmt.Println(err.Error())
		return err
	}
	h.FptrDriver = fptr
	return nil
}

func (h *Handler) sendHandleError(w http.ResponseWriter, message string, messageID string) {
	// Логирование ошибки перед отправкой клиенту
	fmt.Printf("handler ошибка [%s]: %s", messageID, message)
	response := models.WSResponse{
		Type:    "error",
		Message: message,
		ID:      messageID,
		Time:    time.Now().UnixNano() / int64(time.Millisecond),
	}
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(response)
	fmt.Println("ошибка выполнения команды")
}

func (h *Handler) sendHandlerResponse(w http.ResponseWriter, responseType, message string, data interface{}, messageID string) {
	// Логируем отправку ответа
	fmt.Printf("handler ответ [%s]: тип %s", messageID, responseType)

	response := models.WSResponse{
		Type:    responseType,
		Message: message,
		Data:    data,
		ID:      messageID,
		Time:    time.Now().UnixNano() / int64(time.Millisecond),
	}

	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(response)
	fmt.Println("команда успешно выполнена, отправили ответ")
}
