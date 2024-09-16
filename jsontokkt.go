//go:generate ./resource/goversioninfo.exe -icon=resource/icon.ico -manifest=resource/goversioninfo.exe.manifest
package main

import (
	"bufio"
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
	"strconv"
	"strings"

	"github.com/gorilla/websocket"
	"github.com/rs/cors"
)

var clearLogsProgramm = flag.Bool("clearlogs", true, "очистить логи программы")
var LogsDebugs = flag.Int("debug", 3, "уровень логирования всех действий, чем выше тем больше логов")
var comport = flag.Int("com", 0, "ком порт кассы")
var CassirName = flag.String("cassir", "", "имя кассира")
var ipaddresskkt = flag.String("ipkkt", "", "ip адрес ккт")
var portkktatol = flag.Int("portipkkt", 0, "порт ip ккт")
var ipaddressservrkkt = flag.String("ipservkkt", "", "ip адрес сервера ккт")
var emulation = flag.Bool("emul", true, "эмуляция")

//var dontprintrealfortest = flag.Bool("test", false, "тест - не печатать реальный чек")
//var emulatmistakes = flag.Bool("emulmist", false, "эмуляция ошибок")
//var emulatmistakesOpenCheck = flag.Bool("emulmistopencheck", false, "эмуляция ошибок открытия чека")

const Version_of_program = "2024_09_15_01"

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

func main() {
	var err error
	var fptr *fptr10.IFptr
	//выводим информацию о программе
	runDescription := "программа версии " + Version_of_program + " распечатка чеков из json заданий запущена"
	fmt.Println(runDescription)
	defer fmt.Println("программа версии " + Version_of_program + " распечатка чеков из json заданий остановлена")
	//читаем параметры запуска программы
	fmt.Println("парсинг параметров запуска программы")
	flag.Parse()
	fmt.Println("Эмулирование ККТ", *emulation)
	fmt.Println("Уровень логирования: ", *LogsDebugs)
	clearLogsDescr := fmt.Sprintf("Очистить логи программы %v", *clearLogsProgramm)
	fmt.Println(clearLogsDescr)
	//открываем лог файлы
	fmt.Println("инициализация лог файлов программы")
	input := bufio.NewScanner(os.Stdin)
	descrMistake, err := logsmy.InitializationsLogs(*clearLogsProgramm, *LogsDebugs)
	defer logsmy.CloseDescrptorsLogs()
	if err != nil {
		fmt.Fprint(os.Stderr, descrMistake)
		println("Нажмите любую клавишу...")
		input.Scan()
		log.Panic(descrMistake)
	}
	logsmy.LogginInFile(runDescription)
	logsmy.LogginInFile(clearLogsDescr)
	//читаем настроку com - порта в директории json - заданий
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("порт кассы", *comport)
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Тип кассы atol")
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("инициализация драйвера атол")
	fptr, err = fptr10.NewSafe()
	if err != nil {
		descrError := fmt.Sprintf("Ошибка (%v) иницилизации драйвера ККТ атол", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(descrError)
		println("Нажмите любую клавишу...")
		input.Scan()
		log.Panic(descrError)
	}
	defer fptr.Destroy()
	fmt.Println(fptr.Version())

	mux := http.NewServeMux()
	mux.HandleFunc("/ws", handleWebSocket)

	// Настройка CORS
	c := cors.New(cors.Options{
		AllowedOrigins:      []string{"*"}, // Разрешаем все источники
		AllowedMethods:      []string{"GET", "POST", "OPTIONS"},
		AllowedHeaders:      []string{"Content-Type", "Authorization"},
		AllowCredentials:    true,
		AllowPrivateNetwork: true,
	})

	// Оборачиваем наш mux в CORS handler
	handler := c.Handler(mux)

	fmt.Println("Сервер запущен на http://localhost:8081")
	log.Fatal(http.ListenAndServe("0.0.0.0:8081", handler))
} //main

func handleWebSocket(w http.ResponseWriter, r *http.Request) {
	conn, err := upgrader.Upgrade(w, r, nil)
	if err != nil {
		log.Println("Ошибка при установке WebSocket соединения:", err)
		return
	}
	defer conn.Close()

	for {
		_, p, err := conn.ReadMessage()
		if err != nil {
			log.Println("Ошибка при чтении сообщения:", err)
			return
		}

		var message struct {
			Command string      `json:"command"`
			Data    interface{} `json:"data"`
		}

		if err := json.Unmarshal(p, &message); err != nil {
			log.Println("Ошибка при разборе JSON:", err)
			continue
		}

		switch message.Command {
		case "printCheck":
			var checkData CheckData
			checkDataJSON, _ := json.Marshal(message.Data)
			if err := json.Unmarshal(checkDataJSON, &checkData); err != nil {
				log.Println("Ошибка при разборе данных чека:", err)
				sendWebSocketError(conn, "Ошибка при разборе данных чека")
				continue
			}

			fdn, err := printCheck(checkData)
			if err != nil {
				log.Println("Ошибка при печати чека:", err)
				sendWebSocketError(conn, fmt.Sprintf("Ошибка печати чека: %v", err))
			} else {
				sendWebSocketResponse(conn, "Чек успешно напечатан", fdn)
			}

		case "closeShift":
			var requestData struct {
				Cashier string `json:"cashier"`
			}
			dataJSON, _ := json.Marshal(message.Data)
			if err := json.Unmarshal(dataJSON, &requestData); err != nil {
				log.Println("Ошибка при разборе данных закрытия смены:", err)
				sendWebSocketError(conn, "Ошибка при разборе данных закрытия смены")
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
		Type:    "printCheckResponse",
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

	fptr, err = fptr10.NewSafe()
	if err != nil {
		return 0, fmt.Errorf("ошибка инициализации драйвера ККТ: %v", err)
	}
	defer fptr.Destroy()

	// Подключение к кассе
	if ok, typepodkluch := connectWithKassa(fptr, *comport, *ipaddresskkt, *portkktatol, *ipaddressservrkkt); !ok {
		if !*emulation {
			return 0, fmt.Errorf("ошибка подключения к кассе: %v", typepodkluch)
		}
	}
	defer fptr.Close()

	// Проверка открытия смены
	_, err = checkOpenShift(fptr, true, checkData.Cashier)
	if err != nil {
		if !*emulation {
			return 0, fmt.Errorf("ошибка проверки/открытия смены: %v", err)
		}
	}

	// Формирование JSON для печати чека
	checkJSON := formatCheckJSON(checkData)

	// Отправка команды печати чека
	result, err := sendComandeAndGetAnswerFromKKT(fptr, checkJSON)
	fmt.Println("получили результат отправки команды печати чека", result)
	if err != nil {
		return 0, fmt.Errorf("ошибка отправки команды печати чека: %v", err)
	}

	if !successCommand(result) {
		return 0, fmt.Errorf("ошибка печати чека: %v", result)
	}

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
	res := true
	indOsh := strings.Contains(resulJson, "ошибка")
	indErr := strings.Contains(resulJson, "error")
	if indErr || indOsh {
		res = false
	}
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
	logsmy.LogginInFile("получаем статус ККТ")
	fmt.Println("получаем статус ККТ")
	getStatusKKTJson := "{\"type\": \"getDeviceStatus\"}"
	resgetStatusKKT, err := sendComandeAndGetAnswerFromKKT(fptr, getStatusKKTJson)
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
	var answerOfGetStatusofShift consttypes.TAnswerGetStatusOfShift
	err = json.Unmarshal([]byte(resgetStatusKKT), &answerOfGetStatusofShift)
	if err != nil {
		errorDescr := fmt.Sprintf("ошибка (%v) распарсивания статуса кассы", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
		return false, err
	}
	if answerOfGetStatusofShift.ShiftStatus.State == "expired" {
		errorDescr := "ошибка - смена на кассе уже истекла. Закройте смену"
		logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
		return false, errors.New(errorDescr)
	}
	if answerOfGetStatusofShift.ShiftStatus.State == "closed" {
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
		fmt.Println("ошибка отправки команды печати X-отчета: %v", err)
		return fmt.Errorf("ошибка отправки команды печати X-отчета: %v", err)
	}

	if !successCommand(result) {
		fmt.Println("ошибка печати X-отчета: %v", result)
		return fmt.Errorf("ошибка печати X-отчета: %v", result)
	}

	return nil
}
