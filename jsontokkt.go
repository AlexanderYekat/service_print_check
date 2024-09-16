//go:generate ./resource/goversioninfo.exe -icon=resource/icon.ico -manifest=resource/goversioninfo.exe.manifest
package main

import (
	consttypes "clientrabbit/consttypes"
	fptr10 "clientrabbit/fptr"
	logsmy "clientrabbit/packetlog"
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"

	"github.com/gorilla/websocket"
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

//var dontprintrealfortest = flag.Bool("test", false, "тест - не печатать реальный чек")
//var emulatmistakes = flag.Bool("emulmist", false, "эмуляция ошибок")
//var emulatmistakesOpenCheck = flag.Bool("emulmistopencheck", false, "эмуляция ошибок открытия чека")

const Version_of_program = "2024_09_16_02"

type CheckItem struct {
	Name     string `json:"name"`
	Quantity string `json:"quantity"`
	Price    string `json:"price"`
}

type Payment struct {
	Type   string  `json:"type"`
	Amount float64 `json:"amount"`
}

type CheckData struct {
	TableData []CheckItem `json:"tableData"`
	Cashier   string      `json:"cashier"`
	Payments  []Payment   `json:"payments"`
	Type      string      `json:"type"`
}

var upgrader = websocket.Upgrader{
	CheckOrigin: func(r *http.Request) bool {
		origin := r.Header.Get("Origin")
		log.Printf("Получен WebSocket запрос с origin: %s, URL: %s", origin, r.URL.String())
		return true // Все еще разрешаем все запросы, но теперь логируем их
	},
}

type myService struct{}

func (m *myService) Execute(args []string, r <-chan svc.ChangeRequest, changes chan<- svc.Status) (ssec bool, errno uint32) {
	// Настройка логирования
	logFile, err := os.OpenFile(filepath.Join(os.TempDir(), "CloudPosBridge_service.log"), os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0666)
	if err != nil {
		log.Printf("Ошибка при открытии файла лога: %v", err)
		return
	}
	defer logFile.Close()
	log.SetOutput(logFile)

	log.Println("Служба CloudPosBridge запущена")
	log.Printf("Аргументы запуска: %v", args)

	const cmdsAccepted = svc.AcceptStop | svc.AcceptShutdown
	changes <- svc.Status{State: svc.StartPending}
	log.Println("Статус изменен на StartPending")

	// Попытка инициализации
	log.Println("Начало инициализации")
	// Здесь можно добавить код инициализации, если он есть
	log.Println("Инициализация завершена")

	changes <- svc.Status{State: svc.Running, Accepts: cmdsAccepted}
	log.Println("Статус изменен на Running")

	log.Println("Запуск сервера")
	go func() {
		if err := runServer(); err != nil {
			log.Printf("Ошибка при запуске сервера: %v", err)
		}
	}()

	log.Println("Вход в основной цикл обработки")
	for {
		select {
		case c := <-r:
			switch c.Cmd {
			case svc.Interrogate:
				log.Println("Получена команда Interrogate")
				changes <- c.CurrentStatus
			case svc.Stop, svc.Shutdown:
				log.Printf("Получена команда %v", c.Cmd)
				return
			default:
				log.Printf("Получена неизвестная команда %d", c)
			}
		case <-time.After(5 * time.Second):
			log.Println("Служба все еще работает")
		}
	}
}

func runServer() error {
	log.Println("Функция runServer начала выполнение")
	addr := "localhost:8081"
	log.Printf("Попытка запуска WebSocket сервера на %s", addr)
	http.HandleFunc("/", handleWebSocket)
	log.Println("Начало прослушивания WebSocket соединений")
	err := http.ListenAndServe(addr, nil)
	if err != nil {
		log.Printf("Ошибка при запуске WebSocket сервера: %v", err)
		return err
	}
	log.Println("Функция runServer завершила выполнение")
	return nil
}

func handleWebSocket(w http.ResponseWriter, r *http.Request) {
	log.Printf("Получен запрос на WebSocket соединение от %s", r.RemoteAddr)
	conn, err := upgrader.Upgrade(w, r, nil)
	if err != nil {
		log.Printf("Ошибка при установке WebSocket соединения: %v", err)
		return
	}
	defer conn.Close()
	log.Printf("WebSocket соединение установлено с %s", conn.RemoteAddr())

	// Увеличим таймаут до 5 минут
	conn.SetReadDeadline(time.Now().Add(5 * time.Minute))

	// Добавим пинг-понг
	go func() {
		for {
			time.Sleep(30 * time.Second)
			if err := conn.WriteMessage(websocket.PingMessage, nil); err != nil {
				return
			}
		}
	}()

	for {
		_, p, err := conn.ReadMessage()
		if err != nil {
			if websocket.IsUnexpectedCloseError(err, websocket.CloseGoingAway, websocket.CloseAbnormalClosure) {
				log.Printf("Ошибка при чтении сообщения: %v", err)
			} else {
				log.Printf("Соединение закрыто клиентом: %v", err)
			}
			return
		}
		conn.SetReadDeadline(time.Now().Add(5 * time.Minute))
		log.Printf("Получено сообщение: %s", string(p))

		var message struct {
			Command string      `json:"command"`
			Data    interface{} `json:"data"`
		}

		log.Printf("Unmarshal-ing сообщения: %v", p)
		if err := json.Unmarshal(p, &message); err != nil {
			log.Println("Ошибка при разборе JSON:", err)
			sendWebSocketError(conn, fmt.Sprintf("Ошибка при разборе JSON: %v", err))
			continue
		}
		log.Printf("Unmarshal-ed сообщения: %v", message)

		log.Printf("команда: %v", message.Command)
		switch message.Command {
		case "printCheck":
			var checkData CheckData
			log.Printf("маршалинг данных чека %v", message.Data)
			checkDataJSON, _ := json.Marshal(message.Data)
			log.Printf("отмаршалили данные чека %v", checkDataJSON)
			if err := json.Unmarshal(checkDataJSON, &checkData); err != nil {
				log.Println("Ошибка при разборе данных чека:", err)
				sendWebSocketError(conn, "Ошибка при разборе данных чека")
				continue
			}
			log.Printf("Unmarshal данные чека %v", checkData)

			log.Println("начали выполнение команды печати чека")
			fdn, err := printCheck(checkData)
			if err != nil {
				log.Println("Ошибка при печати чека:", err)
				sendWebSocketError(conn, fmt.Sprintf("Ошибка печати чека: %v", err))
			} else {
				sendWebSocketResponse(conn, "Чек успешно напечатан", fdn)
			}
			log.Println("выполнили команду печати чека")
		case "closeShift":
			var requestData struct {
				Cashier string `json:"cashier"`
			}
			dataJSON, _ := json.Marshal(message.Data)
			if err := json.Unmarshal(dataJSON, &requestData); err != nil {
				log.Println("Ошибка при разборе данных закрытия смены:", err)
				sendWebSocketError(conn, "Ошибка при разборе данных закрыти смены")
				continue
			}

			err := closeShift(requestData.Cashier)
			if err != nil {
				log.Println("Ошибка при закрытии смены:", err)
				sendWebSocketError(conn, fmt.Sprintf("Ошибка закрытия смены: %v", err))
			} else {
				sendWebSocketResponse(conn, "Смена успешно закрыта")
			}

		case "xReport":
			err := printXReport()
			if err != nil {
				log.Println("Ошибка при печати X-отчета:", err)
				sendWebSocketError(conn, fmt.Sprintf("Ошибка печати X-отчета: %v", err))
			} else {
				sendWebSocketResponse(conn, "X-отчет успешно напечатан")
			}

		default:
			log.Println("Неизвестный тип сообщения:", message.Command)
		}
	}
}

func sendWebSocketResponse(conn *websocket.Conn, message string, fiscalDocumentNumber ...int) {
	var fdn int
	if len(fiscalDocumentNumber) > 0 {
		fdn = fiscalDocumentNumber[0]
	}

	response := struct {
		Type    string `json:"type"`
		Data    int    `json:"data"`
		Message string `json:"message"`
	}{
		Type:    "success",
		Data:    fdn,
		Message: message,
	}

	if err := conn.WriteJSON(response); err != nil {
		log.Println("Ошибка при отправке ответа:", err)
	}
}

func sendWebSocketError(conn *websocket.Conn, errorMessage string) {
	response := struct {
		Type    string `json:"type"`
		Message string `json:"message"`
	}{
		Type:    "error",
		Message: errorMessage,
	}
	if err := conn.WriteJSON(response); err != nil {
		log.Println("Ошибка при отправке сообщения об ошибке:", err)
	}
}

func printCheck(checkData CheckData) (int, error) {
	var fptr *fptr10.IFptr
	var err error

	log.Println("начали инициализацию драйвера ККТ")
	fptr, err = fptr10.NewSafe()
	if err != nil {
		return 0, fmt.Errorf("ошибка инициализации драйвера ККТ: %v", err)
	}
	log.Println("завершили инициализацию драйвера ККТ")
	defer fptr.Destroy()

	// Подключение к кассе
	log.Println("начали подключение к кассе")
	if ok, typepodkluch := connectWithKassa(fptr, *comport, *ipaddresskkt, *portkktatol, *ipaddressservrkkt); !ok {
		if !*emulation {
			return 0, fmt.Errorf("ошибка подключения к кассе: %v", typepodkluch)
		}
	}
	log.Println("завершили подключение к кассе")
	defer fptr.Close()

	// Проверка открытия смены
	log.Println("начали проверку/открытие смены. Кассир:", checkData.Cashier)
	_, err = checkOpenShift(fptr, true, checkData.Cashier)
	if err != nil {
		if !*emulation {
			return 0, fmt.Errorf("ошибка проверки/открытия смены: %v", err)
		}
	}
	log.Println("завершили проверку/открытие смены")
	// Формирование JSON для печати чека
	checkJSON := formatCheckJSON(checkData)
	log.Println("начали отправку команды печати чека")
	// Отправка команды печати чека
	result, err := sendComandeAndGetAnswerFromKKT(fptr, checkJSON)
	log.Println("получили результат отправки команды печати чека", result)
	if err != nil {
		return 0, fmt.Errorf("ошибка отправки команды печати чека: %v", err)
	}
	log.Println("завершили отправку команды печати чека")

	if !successCommand(result) {
		return 0, fmt.Errorf("ошибка печати чека: %v", result)
	}
	log.Println("начали преобразование результата отправки команды печати чека в структуру JSON")
	// Преобразуем result в структуру JSON
	var resultJSON struct {
		FiscalDocumentNumber int `json:"fiscalDocumentNumber"`
	}
	err = json.Unmarshal([]byte(result), &resultJSON)
	if err != nil {
		if !*emulation {
			fmt.Println("Ошибка при разборе JSON результата:", err)
			return 0, fmt.Errorf("ошибка при разборе JSON результата: %v", err)
		} else {
			resultJSON.FiscalDocumentNumber = 123
		}
	}
	log.Println("завершили преобразование результата отправки команды печати чека в структуру JSON")
	log.Println("Номер фискального документа:", resultJSON.FiscalDocumentNumber)

	fmt.Println("Номер фискального документа:", resultJSON.FiscalDocumentNumber)
	return resultJSON.FiscalDocumentNumber, nil
}

func formatCheckJSON(checkData CheckData) string {
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

func sendComandeAndGetAnswerFromKKT(fptr *fptr10.IFptr, comJson string) (string, error) {
	var err error
	logsmy.LogginInFile("начало процедуры sendComandeAndGetAnswerFromKKT")
	//return "", nil
	logsmy.LogginInFile("отправка команды на кассу:" + comJson)
	fptr.SetParam(fptr10.LIBFPTR_PARAM_JSON_DATA, comJson)
	//fptr.ValidateJson()
	if !*emulation {
		err = fptr.ProcessJson()
	}
	if err != nil {
		if !*emulation {
			desrError := fmt.Sprintf("ошибка (%v) выполнение команды %v на кассе", err, comJson)
			logsmy.Logsmap[consttypes.LOGERROR].Println(desrError)
			logstr := fmt.Sprint("конец процедуры sendComandeAndGetAnswerFromKKT c ошибкой", err)
			logsmy.LogginInFile(logstr)
			return desrError, err
		}
	}
	result := fptr.GetParamString(fptr10.LIBFPTR_PARAM_JSON_DATA)
	if strings.Contains(result, "Нет связи") {
		logsmy.LogginInFile("нет связи: переподключаемся")
		if ok, typepodkluch := connectWithKassa(fptr, *comport, *ipaddresskkt, *portkktatol, *ipaddressservrkkt); !ok {
			descrErr := fmt.Sprintf("ошибка соединения с кассовым аппаратом %v", typepodkluch)
			logsmy.Logsmap[consttypes.LOGERROR].Println(descrErr)
			if !*emulation {
				println("Нажмите любую клавишу...")
				//input.Scan()
				log.Panic(descrErr)
			}
		} else {
			logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("подключение к кассе на порт %v прошло успешно", *comport)
		}
	}
	logsmy.LogginInFile("конец процедуры sendComandeAndGetAnswerFromKKT без ошибки")
	return result, nil
} //sendComandeAndGetAnswerFromKKT

func successCommand(resulJson string) bool {
	log.Println("начали проверку успешности выполнения команды")
	res := true
	indOsh := strings.Contains(resulJson, "ошибка")
	indErr := strings.Contains(resulJson, "error")
	if indErr || indOsh {
		res = false
	}
	log.Println("завершили проверку успешности выполнения команды")
	return res
} //successCommand

func connectWithKassa(fptr *fptr10.IFptr, comportint int, ipaddresskktper string, portkktper int, ipaddresssrvkktper string) (bool, string) {
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
		log.Println("fptr is nil")
		return false, fmt.Errorf("fptr is nil")
	}

	logsmy.LogginInFile("получаем статус ККТ")
	fmt.Println("получаем статус ККТ")
	getStatusKKTJson := "{\"type\": \"getDeviceStatus\"}"
	log.Println("отправляем команду getDeviceStatus")
	resgetStatusKKT, err := sendComandeAndGetAnswerFromKKT(fptr, getStatusKKTJson)
	log.Println("получили результат отправки команды getDeviceStatus", resgetStatusKKT)
	if err != nil {
		errorDescr := fmt.Sprintf("ошибка (%v) получения статуса кассы", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
		return false, err
	}
	fmt.Println("получили статус кассы")
	if !successCommand(resgetStatusKKT) {
		errorDescr := fmt.Sprintf("ошибка (%v) получения статуса кассы", resgetStatusKKT)
		logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
		//logsmy.LogginInFile(errorDescr)
		return false, errors.New(errorDescr)
	}
	logsmy.LogginInFile("получили статус кассы")
	//проверяем - открыта ли смена
	log.Println("начали распарсивание статуса кассы")
	var answerOfGetStatusofShift consttypes.TAnswerGetStatusOfShift
	err = json.Unmarshal([]byte(resgetStatusKKT), &answerOfGetStatusofShift)
	if err != nil {
		errorDescr := fmt.Sprintf("ошибка (%v) распарсивания статуса кассы", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
		return false, err
	}
	log.Println("завершили распарсивание статуса кассы")
	if answerOfGetStatusofShift.ShiftStatus.State == "expired" {
		errorDescr := "ошибка - смена на кассе уже истекла. Закройте смену"
		logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
		return false, errors.New(errorDescr)
	}
	log.Println("проверяем - закрыта ли смена на кассе")
	if answerOfGetStatusofShift.ShiftStatus.State == "closed" {
		log.Println("смена на кассе закрыта. Открываем смену")
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
		return fmt.Errorf("ошибка инициализации драйвера ККТ: %v", err)
	}
	defer fptr.Destroy()

	if ok, typepodkluch := connectWithKassa(fptr, *comport, *ipaddresskkt, *portkktatol, *ipaddressservrkkt); !ok {
		return fmt.Errorf("ошибка подключения к кассе: %v", typepodkluch)
	}
	defer fptr.Close()

	closeShiftJSON := fmt.Sprintf(`{"type": "closeShift", "operator": {"name": "%s"}}`, cashier)
	result, err := sendComandeAndGetAnswerFromKKT(fptr, closeShiftJSON)
	if err != nil {
		return fmt.Errorf("ошибка отправки команды закрытия смены: %v", err)
	}

	if !successCommand(result) {
		return fmt.Errorf("ошибка закрытия смены: %v", result)
	}

	return nil
}

func printXReport() error {
	fptr, err := fptr10.NewSafe()
	fmt.Println("инициализация драйвера ККТ")
	if err != nil {
		return fmt.Errorf("ошибка инициализации драйвера ККТ: %v", err)
	}
	defer fptr.Destroy()

	fmt.Println("подключение к кассе")
	if ok, typepodkluch := connectWithKassa(fptr, *comport, *ipaddresskkt, *portkktatol, *ipaddressservrkkt); !ok {
		if !*emulation {
			return fmt.Errorf("ошибка подключения к кассе: %v", typepodkluch)
		}
	}
	defer fptr.Close()

	xReportJSON := `{"type": "reportX"}`
	fmt.Println("отправка команды печати X-отчета")
	result, err := sendComandeAndGetAnswerFromKKT(fptr, xReportJSON)
	if err != nil {
		return fmt.Errorf("ошибка отправки команды печати X-отчета: %v", err)
	}

	if !successCommand(result) {
		return fmt.Errorf("ошибка печати X-отчета: %v", result)
	}

	return nil
}

func main() {
	fmt.Println("начало работы программы")
	fmt.Println(os.TempDir())
	if err := consttypes.EnsureLogDirectoryExists(); err != nil {
		log.Fatalf("Не удалось создать директорию для логов: %v", err)
	}
	descrMistake, err := logsmy.InitializationsLogs(*clearLogsProgramm, *LogsDebugs)
	defer logsmy.CloseDescrptorsLogs()
	if err != nil {
		fmt.Fprint(os.Stderr, descrMistake)
		//println("Нажмите любую клавишу...")
		//input.Scan()
		log.Println(descrMistake)
	}

	log.Println("Начало работы программы")
	logsmy.LogginInFile("Начало работы программы")
	isService, err := svc.IsWindowsService()
	if err != nil {
		log.Fatalf("не удалось определить, запущена ли программа как служба: %v", err)
	}
	if isService {
		runService(false)
		return
	}

	// Запускаем как обычное приложение
	runServer()
}

func runService(isDebug bool) {
	elog, err := eventlog.Open("MyService")
	if err != nil {
		return
	}
	defer elog.Close()

	elog.Info(1, "starting service")
	run := svc.Run
	if isDebug {
		run = debug.Run
	}
	err = run("MyService", &myService{})
	if err != nil {
		elog.Error(1, fmt.Sprintf("service failed: %v", err))
		return
	}
	elog.Info(1, "service stopped")
}
