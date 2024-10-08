//go:generate ./resource/goversioninfo.exe -icon=resource/icon.ico -manifest=resource/goversioninfo.exe.manifest
package main

import (
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"io/ioutil"
	"log"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	consttypes "service_print_check/consttypes"
	fptr10 "service_print_check/fptr"
	logsmy "service_print_check/packetlog"
	"strconv"
	"strings"
	"time"

	"github.com/rs/cors"
	"golang.org/x/sys/windows/svc"
	"golang.org/x/sys/windows/svc/debug"
	"golang.org/x/sys/windows/svc/eventlog"
)

var clearLogsProgramm = flag.Bool("clearlogs", true, "очистить логи программы")
var LogsDebugs = flag.Int("debug", 3, "уровень логирования всех действий, чем выше тем больше логов")
var comport = flag.Int("com", 0, "ком порт кассы")
var CassirName = flag.String("cassir", "", "имя кассира")
var ipaddresskkt = flag.String("ipkkt", "", "ip адрес ккт")
var portkktatol = flag.Int("portipkkt", 0, "порт ip ккт")
var ipaddressservrkkt = flag.String("ipservkkt", "", "ip адрес сервера ккт")
var emulation = flag.Bool("emul", false, "эмуляция")
var allowedOrigin = flag.String("allowedOrigin", "", "разрешенный origin для WebSocket соединений")

//var dontprintrealfortest = flag.Bool("test", false, "тест - не печатать реальный чек")
//var emulatmistakes = flag.Bool("emulmist", false, "эмуляция ошибок")
//var emulatmistakesOpenCheck = flag.Bool("emulmistopencheck", false, "эмуляция ошибок открытия чека")

const Version_of_program = "2024_09_21_01"

//fptr.ApplySingleSettings()
//fptr.Open()
//return fptr.IsOpened(), typeConnect

type IFptr10Interface interface {
	//NewSafe() (IFptr10Interface, error)
	Destroy()
	Close() error
	Open() error
	IsOpened() bool
	ApplySingleSettings() error
	SetSingleSetting(name string, value string)
	ProcessJson() error
	GetParamString(name int) string
	SetParam(int32, interface{})
	Version() string
}

type TFptr10Driver struct{}

func (moduleFPRT TFptr10Driver) NewSafe(fptr IFptr10Interface) (IFptr10Interface, error) {
	var err error
	if fptr == nil {
		fptr, err = fptr10.NewSafe()
		if err != nil {
			return nil, err
		}
	}
	return fptr, nil
}

func (moduleFPRT TFptr10Driver) Open(fptr IFptr10Interface) error {
	return fptr.Open()
}

func (moduleFPRT TFptr10Driver) IsOpened(fptr IFptr10Interface) bool {
	return fptr.IsOpened()
}

func (moduleFPRT TFptr10Driver) ApplySingleSettings(fptr IFptr10Interface) error {
	return fptr.ApplySingleSettings()
}

func (moduleFPRT TFptr10Driver) Close(fptr IFptr10Interface) {
	fptr.Close()
}

func (moduleFPRT TFptr10Driver) Destroy(fptr IFptr10Interface) {
	fptr.Destroy()
}

type IAbstractPrinter interface {
	PrintXReport(fptr IFptr10Interface) error
}

type TAbstractPrinter struct{}

var glFptrDriver IFptr10Interface

func (moduleFPRT TAbstractPrinter) PrintXReport(fptr IFptr10Interface) error {
	//logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("инициализация драйвера ККТ")
	//if err != nil {
	//	return fmt.Errorf("ошибка инициализации драйвера ККТ: %v", err)
	//}

	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("подключение к кассе")
	if ok, typepodkluch := connectWithKassa(fptr, *comport, *ipaddresskkt, *portkktatol, *ipaddressservrkkt); !ok {
		if !*emulation {
			return fmt.Errorf("ошибка подключения к кассе: %v", typepodkluch)
		}
	}
	defer fptr.Close()

	xReportJSON := `{"type": "reportX"}`
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("отправка команды печати X-отчета")
	result, err := sendComandeAndGetAnswerFromKKT(fptr, xReportJSON)
	if err != nil {
		return fmt.Errorf("ошибка отправки команды печати X-отчета: %v", err)
	}

	if !successCommand(result) {
		return fmt.Errorf("ошибка печати X-отчета: %v", result)
	}

	return nil
	//return printXReport(fptr10Module) // Вызов вашей существующей функции
}

type TCheckItem struct {
	Name     string `json:"name"`
	Quantity string `json:"quantity"`
	Price    string `json:"price"`
}

type TPayment struct {
	Type   string  `json:"type"`
	Amount float64 `json:"amount"`
}

type TCheckData struct {
	TableData []TCheckItem `json:"tableData"`
	Cashier   string       `json:"cashier"`
	Payments  []TPayment   `json:"payments"`
	Type      string       `json:"type"`
}

type myService struct{}

func (m *myService) Execute(args []string, r <-chan svc.ChangeRequest, changes chan<- svc.Status) (ssec bool, errno uint32) {
	// Настройка логирования
	logsmy.LogginInFile("Execute начало")
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Настройка логирования")
	logFile, err := os.OpenFile(filepath.Join(os.TempDir(), "CloudPosBridge_service.log"), os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0666)
	if err != nil {
		logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при открытии файла лога: %v", err)
		return
	}
	defer logFile.Close()
	log.SetOutput(logFile)

	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Служба CloudPosBridge запущена")
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("Аргументы запуска: %v", args)

	const cmdsAccepted = svc.AcceptStop | svc.AcceptShutdown
	changes <- svc.Status{State: svc.StartPending}
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Статус изменен на StartPending")

	// Попытка инициализации
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Начало инициализации")
	// Здесь можно добавить код инициализации, если он есть
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Инициализация завершена")

	changes <- svc.Status{State: svc.Running, Accepts: cmdsAccepted}
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Статус изменен на Running")

	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Запуск сервера")
	serverErrChan := make(chan error, 1)
	go func() {
		if err := runServerWithRetry(5, 10*time.Second); err != nil {
			serverErrChan <- err
		}
	}()
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Вход в основной цикл обработки")
	for {
		select {
		case c := <-r:
			switch c.Cmd {
			case svc.Interrogate:
				logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Получена команда Interrogate")
				changes <- c.CurrentStatus
			case svc.Stop, svc.Shutdown:
				logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("Получена команда %v", c.Cmd)
				return
			default:
				logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("Получена неизвестная команда %d", c)
			}
		case err := <-serverErrChan:
			logsmy.Logsmap[consttypes.LOGERROR].Printf("Сервер остановился с ошибкой: %v", err)
			return false, 1
		}
	}
}

func runServerWithRetry(maxRetries int, retryInterval time.Duration) error {
	var err error
	for i := 0; i < maxRetries; i++ {
		err = runServer()
		if err == nil {
			return nil
		}
		logsmy.Logsmap[consttypes.LOGERROR].Printf("Попытка %d запуска сервера не удалась: %v. Повтор через %v", i+1, err, retryInterval)
		time.Sleep(retryInterval)
	}
	return fmt.Errorf("не удалось запустить сервер после %d попыток: %v", maxRetries, err)
}

func runServer() error {
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("runServer начало")
	elog, err := eventlog.Open("CloudPosBridge")
	if err != nil {
		logsmy.Logsmap[consttypes.LOGERROR].Printf("Не удалось открыть журнал событий: %v", err)
		return err
	}
	defer elog.Close()

	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Запуск службы CloudPosBridge")
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Функция runServer начала выполнение")
	addr := ":8081"
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("Попытка запуска HTTP сервера на %s", addr)
	realPrinter := &TAbstractPrinter{}
	xReportHandler := handleXReport(realPrinter)

	mux := http.NewServeMux()
	mux.HandleFunc("/api/print-check", handlePrintCheck)
	mux.HandleFunc("/api/close-shift", handleCloseShift)
	mux.Handle("/api/x-report", xReportHandler)
	//mux.HandleFunc("/api/cash-in", handleCashIn)   // Новый обработчик
	//mux.HandleFunc("/api/cash-out", handleCashOut) // Новый обработчик
	//mux.HandleFunc("/", handlermainhtml)
	// Настройка CORS
	c := cors.New(cors.Options{
		//AllowedOrigins: []string{"https://localhost:8443"}, // Разрешаем все источники
		//AllowedOrigins: []string{"http://localhost:8080", "http://188.225.31.209:8080"},
		//AllowedOrigins: []string{"http://188.225.31.209:8443"},
		AllowedOrigins: []string{"https://188.225.31.209:8443"},
		//AllowedOrigins: []string{"http://127.0.0.1:8080"},
		AllowedMethods: []string{"POST", "OPTIONS"},
		AllowedHeaders: []string{"content-type", "access-control-request-private-network"},
		//AllowedHeaders: []string{"content-type", "Access-Control-Allow-Private-Network, Access-Control-Allow-Origin"},
		//AllowedHeaders: []string{"Access-Control-Allow-Private-Network"},
		//AllowCredentials:    true,
		AllowPrivateNetwork: true, // Добавляем это
		//Debug:               true,
		//AllowOriginRequestFunc: func(r *http.Request, origin string) bool {
		//	fmt.Println("********************origin******************", origin)
		//	return origin == "https://188.225.31.209:8443"
		//},
	})
	handler := c.Handler(mux)
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("handler", handler)

	fmt.Printf("Сервер запущен на %v\n", addr)
	err = http.ListenAndServe(addr, handler)
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("err", err)
	if err != nil {
		logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при запуске HTTP сервера: %v", err)
		return err
	}

	//http.HandleFunc("/api/print-check", handlePrintCheck)
	//http.HandleFunc("/api/close-shift", handleCloseShift)
	//http.HandleFunc("/api/x-report", xReportHandler)

	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Начало прослушивания HTTP соединений")
	//err := http.ListenAndServe(addr, nil)
	//if err != nil {
	//	logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при запуске HTTP сервера: %v", err)
	//	return err
	//}
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Функция runServer завершила выполнение")
	return nil
}

func handlePrintCheck(w http.ResponseWriter, r *http.Request) {
	if r.Method == "OPTIONS" {
		w.Header().Set("Access-Control-Allow-Methods", "POST, OPTIONS")
		w.Header().Set("Access-Control-Allow-Headers", "access-control-request-private-network, content-type")
		w.Header().Set("Access-Control-Allow-Origin", "https://188.225.31.209:8443/")

		fmt.Println("Получен OPTIONS запрос")
		w.WriteHeader(http.StatusOK)
		return
	}

	if r.Method != http.MethodPost {
		http.Error(w, "Метод не поддерживается", http.StatusMethodNotAllowed)
		return
	}

	var checkData TCheckData
	if err := json.NewDecoder(r.Body).Decode(&checkData); err != nil {
		http.Error(w, "Ошибка разбора JSON", http.StatusBadRequest)
		return
	}

	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("начали выполнение команды печати чека")
	fdn, err := printCheck(checkData)
	if err != nil {
		logsmy.Logsmap[consttypes.LOGERROR].Println("Ошибка при печати чека:", err)
		http.Error(w, fmt.Sprintf("Ошибка печати чека: %v", err), http.StatusInternalServerError)
		return
	}

	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]interface{}{
		"status":  "success",
		"message": "Чек успешно напечатан",
		"fdn":     fdn,
	})
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("выполнили команду печати чека")
}

func handleCloseShift(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "Метод не поддерживается", http.StatusMethodNotAllowed)
		return
	}

	var requestData struct {
		Cashier string `json:"cashier"`
	}
	if err := json.NewDecoder(r.Body).Decode(&requestData); err != nil {
		logsmy.LogginInFile(fmt.Sprintf("Ошибка разбора JSON: %v", err))
		http.Error(w, "Ошибка разбора JSON", http.StatusBadRequest)
		return
	}

	logsmy.LogginInFile(fmt.Sprintf("начали выполнение команды закрытия смены. Кассир: %s", requestData.Cashier))
	err := closeShift(requestData.Cashier)
	if err != nil {
		logsmy.LogginInFile(fmt.Sprintf("Ошибка при закрытии смены: %v", err))
		http.Error(w, fmt.Sprintf("Ошибка закрытия смены: %v", err), http.StatusInternalServerError)
		return
	}

	logsmy.LogginInFile(fmt.Sprintf("завершили выполнение команды закрытия смены"))
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]string{"status": "success", "message": "Смена успешно закрыта"})
}

// handleXReport is an HTTP handler function that prints an X-report.
//
// It expects a POST request and returns a JSON response indicating the status of the X-report printing operation.
// If an error occurs during the printing, it returns a 500 Internal Server Error response with the error message.
// If the printing is successful, it returns a 200 OK response with a JSON object containing the status and a success message.
func handleXReport(printer IAbstractPrinter) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		//func handleXReport(w http.ResponseWriter, r *http.Request) {
		//logger := log.New(os.Stdout, "", log.LstdFlags)
		//logger.Println("Начало выполнения функции handleXReport")
		if r.Method != http.MethodPost {
			http.Error(w, "Метод не поддерживается", http.StatusMethodNotAllowed)
			return
		}

		err := printer.PrintXReport(glFptrDriver)
		fmt.Println("err handleXReport", err)
		if err != nil {
			logsmy.Logsmap[consttypes.LOGERROR].Println("Ошибка при печати X-отчета:", err)
			http.Error(w, fmt.Sprintf("Ошибка печати X-отчета: %v", err), http.StatusInternalServerError)
			return
		}

		w.WriteHeader(http.StatusOK)
		json.NewEncoder(w).Encode(map[string]string{"status": "success", "message": "X-отчет успешно напечатан"})
	}
}

func printCheck(checkData TCheckData) (int, error) {
	var fptr *fptr10.IFptr
	var err error

	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("начали инициализацию драйвера ККТ")
	fptr, err = fptr10.NewSafe()
	if err != nil {
		logsmy.LogginInFile(fmt.Sprintf("ошибка инициализации драйвера ККТ: %v", err))
		return 0, fmt.Errorf("ошибка инициализации драйвера ККТ: %v", err)
	}
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("завершили инициализацию драйвера ККТ")
	defer func() {
		logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("начали уничтожение драйвера ККТ")
		fptr.Destroy()
		logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("завершили уничтожение драйвера ККТ")
	}()

	// Подключение к кассе
	logsmy.LogginInFile("начали подключение к кассе")
	if ok, typepodkluch := connectWithKassa(fptr, *comport, *ipaddresskkt, *portkktatol, *ipaddressservrkkt); !ok {
		logsmy.LogginInFile(fmt.Sprintf("ошибка подключения к кассе: %v", typepodkluch))
		if !*emulation {
			return 0, fmt.Errorf("ошибка подключения к кассе: %v", typepodkluch)
		}
	}
	logsmy.LogginInFile("подключение к кассе прошло успешно")
	defer func() {
		logsmy.LogginInFile("отключение от кассы")
		if err := fptr.Close(); err != nil {
			logsmy.Logsmap[consttypes.LOGERROR].Printf("ошибка закрытия соединения с кассой: %v", err)
		}
	}()

	// Проверка открытия смены
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("начали проверку/открытие смены. Кассир: %s", checkData.Cashier)
	_, err = checkOpenShift(fptr, true, checkData.Cashier)
	if err != nil {
		logsmy.LogginInFile(fmt.Sprintf("ошибка проверки/открытия смены: %v", err))
		if !*emulation {
			return 0, fmt.Errorf("ошибка проверки/открытия смены: %v", err)
		}
	}
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("завершили проверку/открытие смены")
	// Формирование JSON для печати чека
	checkJSON := formatCheckJSON(checkData)
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("начали отправку команды печати чека")
	// Отправка команды печати чека
	result, err := sendComandeAndGetAnswerFromKKT(fptr, checkJSON)
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("получили результат отправки команды печати чека: %s", result)
	if err != nil {
		logsmy.LogginInFile(fmt.Sprintf("ошибка отправки команды печати чека: %v", err))
		return 0, fmt.Errorf("ошибка отправки команды печати чека: %v", err)
	}
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("завершили отправку команды печати чека")

	if !successCommand(result) {
		logsmy.LogginInFile(fmt.Sprintf("ошибка печати чека: %v", result))
		return 0, fmt.Errorf("ошибка печати чека: %v", result)
	}
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("начали преобразование результата отправки команды печати чека в структуру JSON")
	// Преобразуем result в структуру JSON
	var resultJSON struct {
		FiscalDocumentNumber int `json:"fiscalDocumentNumber"`
	}
	err = json.Unmarshal([]byte(result), &resultJSON)
	if err != nil {
		logsmy.LogginInFile(fmt.Sprintf("ошибка при разборе JSON результата: %v", err))
		if !*emulation {
			logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при разборе JSON результата: %v", err)
			return 0, fmt.Errorf("ошибка при разборе JSON результата: %v", err)
		} else {
			resultJSON.FiscalDocumentNumber = 123
		}
	}
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("завершили преобразование результата отправки команды печати чека в структуру JSON")
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("Номер фискального документа: %d", resultJSON.FiscalDocumentNumber)

	fmt.Println("Номер фискального документа:", resultJSON.FiscalDocumentNumber)
	return resultJSON.FiscalDocumentNumber, nil
}

func formatCheckJSON(checkData TCheckData) string {
	// Здесь формируем JSON для печати чека в соответствии с форматом, ожидаемым ККТ
	// Пример:
	checkItems := make([]map[string]interface{}, len(checkData.TableData))
	for i, item := range checkData.TableData {
		quantity, _ := strconv.ParseFloat(item.Quantity, 64)
		price, _ := strconv.ParseFloat(item.Price, 64)
		checkItems[i] = map[string]interface{}{
			"type":     "position",
			"name":     item.Name,
			"price":    price,
			"quantity": quantity,
			"amount":   price * quantity,
		}
	}

	// Формируем массив оплат
	payments := make([]map[string]interface{}, 0)
	totalAmount := 0.0

	// Вычисляем общую сумму чека
	for _, item := range checkData.TableData {
		quantity, _ := strconv.ParseFloat(item.Quantity, 64)
		price, _ := strconv.ParseFloat(item.Price, 64)
		totalAmount += quantity * price
	}

	if len(checkData.Payments) == 0 {
		// Если платежи не переданы, используем оплату наличными по умолчанию
		payments = append(payments, map[string]interface{}{
			"type": "cash",
			"sum":  totalAmount,
		})
	} else {
		for _, payment := range checkData.Payments {
			payments = append(payments, map[string]interface{}{
				"type": payment.Type,
				"sum":  payment.Amount,
			})
		}
	}

	checkType := "sell"
	if checkData.Type != "" {
		checkType = checkData.Type
	}

	checkJSON := map[string]interface{}{
		"type": checkType,
		"operator": map[string]string{
			"name": checkData.Cashier,
		},
		"items":    checkItems,
		"payments": payments,
	}

	jsonBytes, _ := json.Marshal(checkJSON)
	return string(jsonBytes)
}

func sendComandeAndGetAnswerFromKKT(fptr IFptr10Interface, comJson string) (string, error) {
	var err error
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("начало процедуры sendComandeAndGetAnswerFromKKT")
	//return "", nil
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("отправка команды на кассу: %s", comJson)
	fptr.SetParam(fptr10.LIBFPTR_PARAM_JSON_DATA, comJson)
	//fptr.ValidateJson()
	if !*emulation {
		err = fptr.ProcessJson()
	}
	if err != nil {
		if !*emulation {
			desrError := fmt.Sprintf("ошибка (%v) выполнение команды %v на кассе", err, comJson)
			logsmy.Logsmap[consttypes.LOGERROR].Println(desrError)
			logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("конец процедуры sendComandeAndGetAnswerFromKKT c ошибкой: %v", err)
			return desrError, err
		}
	}
	result := fptr.GetParamString(fptr10.LIBFPTR_PARAM_JSON_DATA)
	if strings.Contains(result, "Нет связи") {
		logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("нет связи: переподключаемся")
		if ok, typepodkluch := connectWithKassa(fptr, *comport, *ipaddresskkt, *portkktatol, *ipaddressservrkkt); !ok {
			descrErr := fmt.Sprintf("ошибка соединения с кассовым аппаратом %v", typepodkluch)
			fmt.Println(descrErr)
			logsmy.Logsmap[consttypes.LOGERROR].Println(descrErr)
			if !*emulation {
				logsmy.LogginInFile(descrErr)
				println("Нажмите любую клавишу...")
				//input.Scan()
				//logsmy.Logsmap[consttypes.LOGERROR].Panic(descrErr)
			}
		} else {
			logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("подключение к кассе а порт %v прошло успешно", *comport)
		}
	}
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("конец процедуры sendComandeAndGetAnswerFromKKT без ошибки")
	return result, nil
} //sendComandeAndGetAnswerFromKKT

func successCommand(resulJson string) bool {
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("начали проверку успешности выполнения команды")
	res := true
	indOsh := strings.Contains(resulJson, "ошибка")
	indErr := strings.Contains(resulJson, "error")
	if indErr || indOsh {
		res = false
	}
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("завершили проверку успешности ыполнения команды")
	return res
} //successCommand

func connectWithKassa(fptr IFptr10Interface, comportint int, ipaddresskktper string, portkktper int, ipaddresssrvkktper string) (bool, string) {
	//if !strings.Contains(comport, "COM") {
	//	sComPorta = "COM" + comport
	//}
	typeConnect := ""
	fptr.SetSingleSetting(fptr10.LIBFPTR_SETTING_MODEL, strconv.Itoa(fptr10.LIBFPTR_MODEL_ATOL_AUTO))
	if ipaddresssrvkktper != "" {
		fptr.SetSingleSetting(fptr10.LIBFPTR_SETTING_REMOTE_SERVER_ADDR, ipaddresssrvkktper)
		typeConnect = fmt.Sprintf("через сервер ККТ по IP %v", ipaddresssrvkktper)
	}
	if comportint == 0 {
		if ipaddresskktper != "" {
			fptr.SetSingleSetting(fptr10.LIBFPTR_SETTING_PORT, strconv.Itoa(fptr10.LIBFPTR_PORT_TCPIP))
			fptr.SetSingleSetting(fptr10.LIBFPTR_SETTING_IPADDRESS, ipaddresskktper)
			typeConnect = fmt.Sprintf("%v по IP %v ККТ на порт %v", typeConnect, ipaddresskktper, portkktper)
			if portkktper != 0 {
				fptr.SetSingleSetting(fptr10.LIBFPTR_SETTING_IPPORT, strconv.Itoa(portkktper))
			}
		} else {
			fptr.SetSingleSetting(fptr10.LIBFPTR_SETTING_PORT, strconv.Itoa(fptr10.LIBFPTR_PORT_USB))
			typeConnect = fmt.Sprintf("%v по USB", typeConnect)
		}
	} else {
		sComPorta := "COM" + strconv.Itoa(comportint)
		typeConnect = fmt.Sprintf("%v по COM порту %v", typeConnect, sComPorta)
		fptr.SetSingleSetting(fptr10.LIBFPTR_SETTING_PORT, strconv.Itoa(fptr10.LIBFPTR_PORT_COM))
		fptr.SetSingleSetting(fptr10.LIBFPTR_SETTING_COM_FILE, sComPorta)
		fptr.SetSingleSetting(fptr10.LIBFPTR_SETTING_BAUDRATE, strconv.Itoa(fptr10.LIBFPTR_PORT_BR_115200))
	}
	fptr.ApplySingleSettings()
	fptr.Open()
	return fptr.IsOpened(), typeConnect
}

func checkOpenShift(fptr *fptr10.IFptr, openShiftIfClose bool, kassir string) (bool, error) {
	if fptr == nil {
		logsmy.Logsmap[consttypes.LOGERROR].Println("fptr is nil")
		return false, fmt.Errorf("fptr is nil")
	}

	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("получаем статус ККТ")
	fmt.Println("получаем статус ККТ")
	getStatusKKTJson := "{\"type\": \"getDeviceStatus\"}"
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("отправляем команду getDeviceStatus")
	resgetStatusKKT, err := sendComandeAndGetAnswerFromKKT(fptr, getStatusKKTJson)
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("получили результат отправки команды getDeviceStatus: %s", resgetStatusKKT)
	if err != nil {
		errorDescr := fmt.Sprintf("ошибка (%v) получения статуса кассы", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
		return false, err
	}
	fmt.Println("получили статус кассы")
	if !successCommand(resgetStatusKKT) {
		errorDescr := fmt.Sprintf("ошибка (%v) получения статуса кассы", resgetStatusKKT)
		logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
		logsmy.LogginInFile(errorDescr)
		return false, errors.New(errorDescr)
	}
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("получили статус кассы")
	//проверяем - открыта ли смена
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("начали распарсивание статуса кассы")
	var answerOfGetStatusofShift consttypes.TAnswerGetStatusOfShift
	err = json.Unmarshal([]byte(resgetStatusKKT), &answerOfGetStatusofShift)
	if err != nil {
		errorDescr := fmt.Sprintf("ошибка (%v) распарсивания статуса кассы", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
		return false, err
	}
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("завершили распарсивание статуса кассы")
	if answerOfGetStatusofShift.ShiftStatus.State == "expired" {
		errorDescr := "ошибка - смена на кассе уже истекла. Закройте смену"
		logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
		return false, errors.New(errorDescr)
	}
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("проверяем - закрыта ли смена на кассе")
	if answerOfGetStatusofShift.ShiftStatus.State == "closed" {
		logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("смена на кассе закрыта. Открываем смену")
		if openShiftIfClose {
			if kassir == "" {
				errorDescr := "не указано имя кассира для открытия смены"
				logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
				return false, errors.New(errorDescr)
			}
			jsonOpenShift := fmt.Sprintf("{\"type\": \"openShift\",\"operator\": {\"name\": \"%v\"}}", kassir)
			resOpenShift, err := sendComandeAndGetAnswerFromKKT(fptr, jsonOpenShift)
			if err != nil {
				errorDescr := fmt.Sprintf("ошбика (%v) - не удалось открыть смену", err)
				logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
				return false, errors.New(errorDescr)
			}
			if !successCommand(resOpenShift) {
				errorDescr := fmt.Sprintf("ошбика (%v) - не удалось открыть смену", resOpenShift)
				logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
				return false, errors.New(errorDescr)
			}
		} else {
			return false, nil
		}
	}
	return true, nil
} //checkOpenShift

func closeShift(cashier string) error {
	fptr, err := fptr10.NewSafe()
	if err != nil {
		logsmy.LogginInFile(fmt.Sprintf("ошибка инициализации драйвера ККТ: %v", err))
		return fmt.Errorf("ошибка инициализации драйвера ККТ: %v", err)
	}
	defer func() {
		if fptr != nil {
			logsmy.LogginInFile("выполняем освобождение ресурсов драйвера ККТ")
			fptr.Destroy()
			logsmy.LogginInFile("освобождение ресурсов драйвера ККТ выполнено")
		}
	}()

	if ok, typepodkluch := connectWithKassa(fptr, *comport, *ipaddresskkt, *portkktatol, *ipaddressservrkkt); !ok {
		logsmy.LogginInFile(fmt.Sprintf("ошибка подключения к кассе: %v", typepodkluch))
		return fmt.Errorf("ошибка подключения к кассе: %v", typepodkluch)
	}
	defer func() {
		if fptr != nil {
			logsmy.LogginInFile("выполняем закрытие соединения с кассой")
			fptr.Close()
			logsmy.LogginInFile("закрытие соединения с кассой выполнено")
		}
	}()

	closeShiftJSON := fmt.Sprintf(`{"type": "closeShift", "operator": {"name": "%s"}}`, cashier)
	result, err := sendComandeAndGetAnswerFromKKT(fptr, closeShiftJSON)
	if err != nil {
		logsmy.LogginInFile(fmt.Sprintf("ошибка отправки команды закрытия смены: %v", err))
		return fmt.Errorf("ошибка отправки команды закрытия смены: %v", err)
	}

	if !successCommand(result) {
		logsmy.LogginInFile(fmt.Sprintf("ошибка закрытия смены: %v", result))
		return fmt.Errorf("ошибка закрытия смены: %v", result)
	}

	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("смена закрыта успешно")
	return nil
}

// Структура для хранения настроек
type Settings struct {
	ClearLogs     bool   `json:"clearlogs"`
	Debug         int    `json:"debug"`
	Com           int    `json:"com"`
	Cassir        string `json:"cassir"`
	IpKKT         string `json:"ipkkt"`
	PortKKT       int    `json:"portipkkt"`
	IpServKKT     string `json:"ipservkkt"`
	Emulation     bool   `json:"emul"`
	AllowedOrigin string `json:"allowedOrigin"`
}

var currentSettings Settings

// Обработчик для получения текущих настроек
func getSettingsHandler(w http.ResponseWriter, r *http.Request) {
	json.NewEncoder(w).Encode(currentSettings)
}

// Обработчик для сохранения настроек
func saveSettingsHandler(w http.ResponseWriter, r *http.Request) {
	body, err := ioutil.ReadAll(r.Body)
	if err != nil {
		http.Error(w, "Ошибка чтения тела запроса", http.StatusBadRequest)
		return
	}

	err = json.Unmarshal(body, &currentSettings)
	if err != nil {
		http.Error(w, "Ошибка разбора JSON", http.StatusBadRequest)
		return
	}

	// Обновляем глобальные переменные
	*clearLogsProgramm = currentSettings.ClearLogs
	*LogsDebugs = currentSettings.Debug
	*comport = currentSettings.Com
	*CassirName = currentSettings.Cassir
	*ipaddresskkt = currentSettings.IpKKT
	*portkktatol = currentSettings.PortKKT
	*ipaddressservrkkt = currentSettings.IpServKKT
	*emulation = currentSettings.Emulation
	*allowedOrigin = currentSettings.AllowedOrigin

	// Здесь вы можете добавить логику для сохранения настроек в файл или базу данных

	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]string{"status": "success"})
}

func restartServiceHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != "POST" {
		http.Error(w, "Метод не разрешен", http.StatusMethodNotAllowed)
		return
	}

	var cmd *exec.Cmd
	serviceName := "CloudPosBridge"

	if runtime.GOOS == "windows" {
		// Останавливаем службу
		cmd = exec.Command("net", "stop", serviceName)
		err := cmd.Run()
		if err != nil {
			logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при остановке службы: %v", err)
			w.WriteHeader(http.StatusInternalServerError)
			json.NewEncoder(w).Encode(map[string]string{"status": "error", "message": err.Error()})
			return
		}

		// Запускаем службу
		cmd = exec.Command("net", "start", serviceName)
		err = cmd.Run()
		if err != nil {
			logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при запуске службы: %v", err)
			w.WriteHeader(http.StatusInternalServerError)
			json.NewEncoder(w).Encode(map[string]string{"status": "error", "message": err.Error()})
			return
		}
	} else {
		// Для других ОС оставляем текущую реализацию
		cmd = exec.Command("systemctl", "restart", serviceName+".service")
		err := cmd.Run()
		if err != nil {
			logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при перезапуске службы: %v", err)
			w.WriteHeader(http.StatusInternalServerError)
			json.NewEncoder(w).Encode(map[string]string{"status": "error", "message": err.Error()})
			return
		}
	}

	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]string{"status": "success"})
}

var logFilePath string

func getLogPathHandler(w http.ResponseWriter, r *http.Request) {
	w.Write([]byte(logFilePath))
}

func openLogsHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != "POST" {
		http.Error(w, "Метод не разрешен", http.StatusMethodNotAllowed)
		return
	}

	logDir := filepath.Dir(logFilePath)
	var cmd *exec.Cmd

	switch runtime.GOOS {
	case "windows":
		cmd = exec.Command("explorer", logDir)
	case "darwin":
		cmd = exec.Command("open", logDir)
	case "linux":
		cmd = exec.Command("xdg-open", logDir)
	default:
		http.Error(w, "Неподдерживаемая операционная система", http.StatusInternalServerError)
		return
	}

	err := cmd.Start()
	if err != nil {
		http.Error(w, "Ошибка при открытии папки с логами", http.StatusInternalServerError)
		return
	}

	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]string{"status": "success"})
}

func openBrowser(url string) error {
	var err error
	switch runtime.GOOS {
	case "linux":
		err = exec.Command("xdg-open", url).Start()
	case "windows":
		err = exec.Command("rundll32", "url.dll,FileProtocolHandler", url).Start()
	case "darwin":
		err = exec.Command("open", url).Start()
	default:
		err = fmt.Errorf("unsupported platform")
	}
	return err
}

func main() {
	var err error
	execPath, err := os.Executable()
	if err != nil {
		fmt.Println("Ошибка получения пути исполняемого файла:", err)
	}
	fmt.Println("путь исполняемого файла:", execPath)
	fmt.Println("начало работы программы")
	fmt.Println("инициализация драйвера")
	glFptrDriver, err = TFptr10Driver{}.NewSafe(nil)
	if err != nil {
		fmt.Printf("Не удалось создать экземпляр драйвера FPTR: %v", err)
		return
	}
	fmt.Println("инициализация драйвера прошла успешно")
	defer func() {
		if glFptrDriver != nil {
			fmt.Println("Выполняется Destroy()")
			glFptrDriver.Destroy()
			fmt.Println("Destroy() выполнен")
		}
	}()

	fmt.Printf("Версия драйвера: %v\n", glFptrDriver.Version())
	fmt.Println("инициализация директории для логов")
	if err := consttypes.EnsureLogDirectoryExists(); err != nil {
		fmt.Printf("Не удалось создать директорию для логов: %v", err)
	}
	fmt.Println("инициализация директории для логов прошла успешно")
	fmt.Println("инициализация логов")
	descrMistake, logPath, err := logsmy.InitializationsLogs(*clearLogsProgramm, *LogsDebugs)
	defer logsmy.CloseDescrptorsLogs()
	if err != nil {
		fmt.Fprint(os.Stderr, descrMistake)
		logsmy.Logsmap[consttypes.LOGERROR].Println(descrMistake)
		return
	}
	logFilePath = logPath

	logsmy.LogginInFile("Начало работы программы")
	logsmy.LogginInFile(fmt.Sprintf("путь исполняемого файла: %v", execPath))
	logsmy.LogginInFile(fmt.Sprintf("Версия программы: %v", Version_of_program))
	logsmy.LogginInFile(fmt.Sprintf("версия драйвера: %v", glFptrDriver.Version()))
	isService, err := svc.IsWindowsService()
	if err != nil {
		logsmy.Logsmap[consttypes.LOGERROR].Fatalf("не удалось определить, запущена ли программа как служба: %v", err)
	}
	//runServerTest()
	//fmt.Println("завершение работы программы")
	//return
	if isService {
		fmt.Println("запускаем службу")
		runService(false)
		return
	}

	fmt.Println("запускаем как обычное приложение")
	// Запускаем как обычное приложение
	fmt.Println("инициализация http сервера")
	http.HandleFunc("/settings", func(w http.ResponseWriter, r *http.Request) {
		http.ServeFile(w, r, "templates/settings.html")
	})
	http.HandleFunc("/api/settings", func(w http.ResponseWriter, r *http.Request) {
		if r.Method == "GET" {
			getSettingsHandler(w, r)
		} else if r.Method == "POST" {
			saveSettingsHandler(w, r)
		}
	})
	fmt.Println("инициализация http сервера прошла успешно")
	http.HandleFunc("/api/restart", enableCORS(restartServiceHandler))
	http.HandleFunc("/api/logpath", enableCORS(getLogPathHandler))
	http.HandleFunc("/api/openlogs", enableCORS(openLogsHandler))

	// Добавляем обработку статических файлов
	fs := http.FileServer(http.Dir("static"))
	http.Handle("/static/", http.StripPrefix("/static/", fs))

	// Добавляем обработку статических файлов
	fsjs := http.FileServer(http.Dir("static/js"))
	http.Handle("/static/js/", http.StripPrefix("/static/js/", fsjs))

	// Добавляем обработку статических файлов
	fscss := http.FileServer(http.Dir("static/css"))
	http.Handle("/static/css/", http.StripPrefix("/static/css/", fscss))

	// Запускаем сервер в отдельной горутине
	go func() {
		logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Запуск веб-сервера на http://localhost:8080")
		if err := http.ListenAndServe(":8080", nil); err != nil {
			logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка запуска веб-сервера: %v", err)
		}
	}()

	// Даем серверу время на запуск
	time.Sleep(100 * time.Millisecond)

	// Открываем страницу настроек в браузере
	url := "http://localhost:8080/settings"
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("Открытие страницы настроек в браузере: %s", url)
	if err := openBrowser(url); err != nil {
		logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при открытии браузера: %v", err)
	}

	// Держим приложение запущенным
	select {}
}

func runService(isDebug bool) {
	elog, err := eventlog.Open("CloudPosBridge")
	if err != nil {
		logsmy.Logsmap[consttypes.LOGERROR].Printf("Не удалось открыть журнал событий: %v", err)
		return
	}
	defer elog.Close()

	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Запуск службы CloudPosBridge")
	elog.Info(1, "Запуск службы CloudPosBridge")

	run := svc.Run
	if isDebug {
		run = debug.Run
	}

	err = run("CloudPosBridge", &myService{})
	if err != nil {
		errorMsg := fmt.Sprintf("Служба завершилась с ошибкой: %v", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(errorMsg)
		elog.Error(1, errorMsg)
		return
	}

	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Служба CloudPosBridge остановлена")
	elog.Info(1, "Служба CloudPosBridge остановлена")
}

func enableCORS(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Access-Control-Allow-Origin", "https://188.225.31.209:8443/")
		w.Header().Set("Access-Control-Allow-Methods", "POST, OPTIONS")
		w.Header().Set("Access-Control-Allow-Headers", "Content-Type, Access-Control-Allow-Private-Network")

		if r.Method == "OPTIONS" {
			w.WriteHeader(http.StatusOK)
			return
		}

		next.ServeHTTP(w, r)
	}
}

func runServerTest() error {
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Функция runServer начала выполнение")
	addr := ":8081"
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("Попытка запуска TЕСТ HTTP сервера на %s", addr)
	realPrinter := &TAbstractPrinter{}
	xReportHandler := handleXReport(realPrinter)

	mux := http.NewServeMux()
	mux.HandleFunc("/api/print-check", handlePrintCheck)
	mux.HandleFunc("/api/close-shift", handleCloseShift)
	mux.Handle("/api/x-report", xReportHandler)
	//mux.HandleFunc("/api/cash-in", handleCashIn)   // Новый обработчик
	//mux.HandleFunc("/api/cash-out", handleCashOut) // Новый обработчик
	//mux.HandleFunc("/", handlermainhtml)
	// Настройка CORS
	c := cors.New(cors.Options{
		//AllowedOrigins: []string{"https://localhost:8443"}, // Разрешаем все источники
		//AllowedOrigins: []string{"http://localhost:8080", "http://188.225.31.209:8080"},
		//AllowedOrigins: []string{"http://188.225.31.209:8443"},
		AllowedOrigins: []string{"https://188.225.31.209:8443"},
		//AllowedOrigins: []string{"http://127.0.0.1:8080"},
		AllowedMethods: []string{"GET", "POST", "PUT", "DELETE", "OPTIONS"},
		AllowedHeaders: []string{"*"},
		Debug:          true,
		AllowOriginRequestFunc: func(r *http.Request, origin string) bool {
			return true
		},
	})

	handler := c.Handler(mux)

	fmt.Printf("Сервер запущен ЕСТ на %v\n", addr)
	err := http.ListenAndServe(addr, handler)
	if err != nil {
		logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при запуске HTTP сервера: %v", err)
		return err
	}

	//http.HandleFunc("/api/print-check", handlePrintCheck)
	//http.HandleFunc("/api/close-shift", handleCloseShift)
	//http.HandleFunc("/api/x-report", xReportHandler)

	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Начало прослушивания HTTP соединений")
	//err := http.ListenAndServe(addr, nil)
	//if err != nil {
	//	logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при запуске HTTP сервера: %v", err)
	//	return err
	//}
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Функция runServer завершила выполнение")
	return nil
}
