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
	"io/ioutil"
	"log"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
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
var allowedOrigin = flag.String("allowedOrigin", "", "разрешенный origin для WebSocket соединений")

//var dontprintrealfortest = flag.Bool("test", false, "тест - не печатать реальный чек")
//var emulatmistakes = flag.Bool("emulmist", false, "эмуляция ошибок")
//var emulatmistakesOpenCheck = flag.Bool("emulmistopencheck", false, "эмуляция ошибок открытия чека")

const Version_of_program = "2024_09_17_01"

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
		logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("Получен WebSocket запрос с origin: %s, URL: %s", origin, r.URL.String())
		if currentSettings.AllowedOrigin != "" {
			return origin == currentSettings.AllowedOrigin
		}
		return true // Разрешаем все запросы, если AllowedOrigin не указан
	},
}

type myService struct{}

func (m *myService) Execute(args []string, r <-chan svc.ChangeRequest, changes chan<- svc.Status) (ssec bool, errno uint32) {
	// Настройка логирования
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
	go func() {
		if err := runServer(); err != nil {
			logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при запуске сервера: %v", err)
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
		case <-time.After(60 * time.Second):
			logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Служба все еще работает")
		}
	}
}

func runServer() error {
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Функция runServer начала выполнение")
	addr := "localhost:8081"
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("Попытка запуска WebSocket сервера на %s", addr)
	http.HandleFunc("/", handleWebSocket)
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Начало прослушивания WebSocket соединений")
	err := http.ListenAndServe(addr, nil)
	if err != nil {
		logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при запуске WebSocket сервера: %v", err)
		return err
	}
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Функция runServer завершила выполнение")
	return nil
}

func handleWebSocket(w http.ResponseWriter, r *http.Request) {
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("Получен запрос на WebSocket соединение от %s", r.RemoteAddr)
	conn, err := upgrader.Upgrade(w, r, nil)
	if err != nil {
		logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при установке WebSocket соединения: %v", err)
		return
	}
	defer conn.Close()
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("WebSocket соединение установлено с %s", conn.RemoteAddr())

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
				logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при чтении сообщения: %v", err)
			} else {
				logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("Соединение закрыто клиентом: %v", err)
			}
			return
		}
		conn.SetReadDeadline(time.Now().Add(5 * time.Minute))
		logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("Получено сообщение: %s", string(p))

		var message struct {
			Command string      `json:"command"`
			Data    interface{} `json:"data"`
		}

		logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("Unmarshal-ing сообщения: %v", p)
		if err := json.Unmarshal(p, &message); err != nil {
			logsmy.Logsmap[consttypes.LOGERROR].Println("Ошибка при разборе JSON:", err)
			sendWebSocketError(conn, fmt.Sprintf("Ошибка при разборе JSON: %v", err))
			continue
		}
		logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("Unmarshal-ed сообщения: %v", message)

		logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("команда: %v", message.Command)
		switch message.Command {
		case "printCheck":
			var checkData CheckData
			logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("маршалинг данных чека %v", message.Data)
			checkDataJSON, _ := json.Marshal(message.Data)
			logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("отмаршалили данные чека %v", checkDataJSON)
			if err := json.Unmarshal(checkDataJSON, &checkData); err != nil {
				logsmy.Logsmap[consttypes.LOGERROR].Println("Ошибка при разборе данных чека:", err)
				sendWebSocketError(conn, "Ошибка при разборе данных чека")
				continue
			}
			logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("Unmarshal данные чека %v", checkData)

			logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("начали выполнение команды печати чека")
			fdn, err := printCheck(checkData)
			if err != nil {
				logsmy.Logsmap[consttypes.LOGERROR].Println("Ошибка при печати чека:", err)
				sendWebSocketError(conn, fmt.Sprintf("Ошибка печати чека: %v", err))
			} else {
				sendWebSocketResponse(conn, "Чек успешно напечатан", fdn)
			}
			logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("выполнили команду печати чека")
		case "closeShift":
			var requestData struct {
				Cashier string `json:"cashier"`
			}
			dataJSON, _ := json.Marshal(message.Data)
			if err := json.Unmarshal(dataJSON, &requestData); err != nil {
				logsmy.Logsmap[consttypes.LOGERROR].Println("Ошибка при разборе данных закрытия смены:", err)
				sendWebSocketError(conn, "Ошибка при разборе данных закрыти смены")
				continue
			}

			err := closeShift(requestData.Cashier)
			if err != nil {
				logsmy.Logsmap[consttypes.LOGERROR].Println("Ошибка при закрытии смены:", err)
				sendWebSocketError(conn, fmt.Sprintf("Ошибка закрытия смены: %v", err))
			} else {
				sendWebSocketResponse(conn, "Смена успешно закрыта")
			}

		case "xReport":
			err := printXReport()
			if err != nil {
				logsmy.Logsmap[consttypes.LOGERROR].Println("Ошибка при печати X-отчета:", err)
				sendWebSocketError(conn, fmt.Sprintf("Ошибка печати X-отчета: %v", err))
			} else {
				sendWebSocketResponse(conn, "X-отчет успешно напечатан")
			}

		default:
			logsmy.Logsmap[consttypes.LOGERROR].Println("Неизвестный тип сообщения:", message.Command)
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
		logsmy.Logsmap[consttypes.LOGERROR].Println("Ошибка при отправке ответа:", err)
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
		logsmy.Logsmap[consttypes.LOGERROR].Println("Ошибка при отправке сообщения об ошибке:", err)
	}
}

func printCheck(checkData CheckData) (int, error) {
	var fptr *fptr10.IFptr
	var err error

	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("начали инициализацию драйвера ККТ")
	fptr, err = fptr10.NewSafe()
	if err != nil {
		return 0, fmt.Errorf("ошибка инициализации драйвера ККТ: %v", err)
	}
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("завершили инициализацию драйвера ККТ")
	defer fptr.Destroy()

	// Подключение к кассе
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("начали подключение к кассе")
	if ok, typepodkluch := connectWithKassa(fptr, *comport, *ipaddresskkt, *portkktatol, *ipaddressservrkkt); !ok {
		if !*emulation {
			return 0, fmt.Errorf("ошибка подключения к кассе: %v", typepodkluch)
		}
	}
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("завершили подключение к кассе")
	defer fptr.Close()

	// Проверка открытия смены
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("начали проверку/открытие смены. Кассир: %s", checkData.Cashier)
	_, err = checkOpenShift(fptr, true, checkData.Cashier)
	if err != nil {
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
		return 0, fmt.Errorf("ошибка отправки команды печати чека: %v", err)
	}
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("завершили отправку команды печати чека")

	if !successCommand(result) {
		return 0, fmt.Errorf("ошибка печати чека: %v", result)
	}
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("начали преобразование результата отправки команды печати чека в структуру JSON")
	// Преобразуем result в структуру JSON
	var resultJSON struct {
		FiscalDocumentNumber int `json:"fiscalDocumentNumber"`
	}
	err = json.Unmarshal([]byte(result), &resultJSON)
	if err != nil {
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
			logsmy.Logsmap[consttypes.LOGERROR].Println(descrErr)
			if !*emulation {
				println("Нажмите любую клавишу...")
				//input.Scan()
				logsmy.Logsmap[consttypes.LOGERROR].Panic(descrErr)
			}
		} else {
			logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("подключение к кассе на порт %v прошло успешно", *comport)
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
		//logsmy.LogginInFile(errorDescr)
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
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("инициализация драйвера ККТ")
	if err != nil {
		return fmt.Errorf("ошибка инициализации драйвера ККТ: %v", err)
	}
	defer fptr.Destroy()

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
	fmt.Println("начало работы программы")
	if err := consttypes.EnsureLogDirectoryExists(); err != nil {
		fmt.Printf("Не удалось создать директорию для логов: %v", err)
	}
	descrMistake, logPath, err := logsmy.InitializationsLogs(*clearLogsProgramm, *LogsDebugs)
	defer logsmy.CloseDescrptorsLogs()
	if err != nil {
		fmt.Fprint(os.Stderr, descrMistake)
		logsmy.Logsmap[consttypes.LOGERROR].Println(descrMistake)
		return
	}
	logFilePath = logPath

	logsmy.LogginInFile("Начало работы программы")
	isService, err := svc.IsWindowsService()
	if err != nil {
		logsmy.Logsmap[consttypes.LOGERROR].Fatalf("не удалось определить, запущена ли программа как служба: %v", err)
	}
	if isService {
		runService(false)
		return
	}

	// Запускаем как обычное приложение
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

	http.HandleFunc("/api/restart", restartServiceHandler)
	http.HandleFunc("/api/logpath", getLogPathHandler)
	http.HandleFunc("/api/openlogs", openLogsHandler)

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
