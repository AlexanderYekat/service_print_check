//go:generate ./resource/goversioninfo.exe -icon=resource/icon.ico -manifest=resource/goversioninfo.exe.manifest
package main

import (
	"bufio"
	consttypes "checkservice/consttypes"
	fptr10 "checkservice/fptr"
	logsmy "checkservice/packetlog"
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"io/ioutil"
	"log"
	"net/http"
	"os"
	"strconv"
	"strings"

	"github.com/rs/cors"
)

var clearLogsProgramm = flag.Bool("clearlogs", true, "очистить логи программы")
var LogsDebugs = flag.Int("debug", 0, "уровень логирования всех действий, чем выше тем больше логов")
var comport = flag.Int("com", 0, "ком порт кассы")
var CassirName = flag.String("cassir", "", "имя кассира")
var ipaddresskkt = flag.String("ipkkt", "", "ip адрес ккт")
var portkktatol = flag.Int("portipkkt", 0, "порт ip ккт")
var ipaddressservrkkt = flag.String("ipservkkt", "", "ip адрес сервера ккт")
var emulation = flag.Bool("emul", false, "эмуляция")
var dontprintrealfortest = flag.Bool("test", false, "тест - не печатать реальный чек")
var emulatmistakes = flag.Bool("emulmist", false, "эмуляция ошибок")
var emulatmistakesOpenCheck = flag.Bool("emulmistopencheck", false, "эмуляция ошибок открытия чека")

const Version_of_program = "2024_07_30_02"

type CheckItem struct {
	Code     string `json:"code"`
	Article  string `json:"article"`
	Name     string `json:"name"`
	Quantity string `json:"quantity"`
	Price    string `json:"price"`
	Sum      string `json:"sum"`
}

type CheckData struct {
	TableData []CheckItem `json:"tableData"`
	Employee  string      `json:"employee"`
	Master    string      `json:"master"`
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
	mux.HandleFunc("/api/print-check", handlePrintCheck)
	//mux.HandleFunc("/api/products", handlePrintCheck)
	mux.HandleFunc("/api/close-shift", handleCloseShift)
	mux.HandleFunc("/api/x-report", handleXReport)

	// Настройка CORS
	c := cors.New(cors.Options{
		AllowedOrigins:      []string{"*"}, // Разрешаем все источники
		AllowedMethods:      []string{"GET", "POST", "OPTIONS"},
		AllowedHeaders:      []string{"Content-Type", "Authorization"},
		AllowCredentials:    true,
		AllowPrivateNetwork: true, // Добавляем это
	})

	// Оборачиваем наш mux в CORS handler
	handler := c.Handler(mux)

	fmt.Println("Настройки CORS:")
	fmt.Printf("AllowedOrigins: %v\n", []string{"*"})
	fmt.Printf("AllowedMethods: %v\n", []string{"GET", "POST", "OPTIONS"})
	fmt.Printf("AllowedHeaders: %v\n", []string{"Content-Type", "Authorization"})
	fmt.Printf("AllowCredentials: %v\n", true)

	fmt.Println("Сервер запущен на http://localhost:8081")
	//log.Fatal(http.ListenAndServe(":8081", handler))
	log.Fatal(http.ListenAndServe("0.0.0.0:8081", handler))
} //main

func handlePrintCheck(w http.ResponseWriter, r *http.Request) {
	fmt.Println("handlePrintCheck вызван")
	fmt.Println("Метод запроса:", r.Method)
	fmt.Println("URL запроса:", r.URL)
	fmt.Println("Заголовки запроса:")
	for name, values := range r.Header {
		for _, value := range values {
			fmt.Printf("%s: %s\n", name, value)
		}
	}
	fmt.Println("Тело запроса:", r.Body)
	fmt.Println("Удаленный адрес:", r.RemoteAddr)
	fmt.Println("URI запроса:", r.RequestURI)
	fmt.Println("Хост:", r.Host)
	fmt.Println("Протокол:", r.Proto)
	fmt.Println("User-Agent:", r.UserAgent())
	fmt.Println("Referer:", r.Referer())
	fmt.Println("Content-Length:", r.ContentLength)
	fmt.Println("Transfer-Encoding:", r.TransferEncoding)
	fmt.Println("Close:", r.Close)
	fmt.Println("Form:", r.Form)

	// Устанавливаем CORS-заголовки
	w.Header().Set("Access-Control-Allow-Origin", "*")
	w.Header().Set("Access-Control-Allow-Methods", "POST, OPTIONS, GET, PUT, DELETE")
	w.Header().Set("Access-Control-Allow-Headers", "Content-Type, Authorization")
	w.Header().Set("Access-Control-Allow-Credentials", "true")
	w.Header().Set("Access-Control-Allow-Private-Network", "true")

	fmt.Println("CORS заголовки установлены")

	// Обрабатываем предварительный запрос OPTIONS
	if r.Method == "OPTIONS" {
		fmt.Println("Получен OPTIONS запрос")
		w.WriteHeader(http.StatusOK)
		return
	}

	if r.Method != http.MethodPost {
		fmt.Println("Метод не поддерживается:", r.Method)
		http.Error(w, "Метод не поддерживается", http.StatusMethodNotAllowed)
		return
	}

	body, err := ioutil.ReadAll(r.Body)
	if err != nil {
		http.Error(w, "Ошибка чтения тела запроса", http.StatusBadRequest)
		return
	}

	var checkData CheckData
	err = json.Unmarshal(body, &checkData)
	if err != nil {
		http.Error(w, "Ошибка разбора JSON", http.StatusBadRequest)
		return
	}

	// Здесь вызываем функцию для печати чека
	err = printCheck(checkData)
	if err != nil {
		http.Error(w, fmt.Sprintf("Ошибка печати чека: %v", err), http.StatusInternalServerError)
		return
	}

	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]string{"status": "success", "message": "Чек успешно напечатан"})
}

func handleCloseShift(w http.ResponseWriter, r *http.Request) {
	// Устанавливаем CORS-заголовки
	w.Header().Set("Access-Control-Allow-Origin", "*")
	w.Header().Set("Access-Control-Allow-Methods", "POST, GET, OPTIONS, PUT, DELETE")
	w.Header().Set("Access-Control-Allow-Headers", "Accept, Content-Type, Content-Length, Accept-Encoding, X-CSRF-Token, Authorization")

	// Обрабатываем предварительный запрос OPTIONS
	if r.Method == "OPTIONS" {
		w.WriteHeader(http.StatusOK)
		return
	}

	if r.Method != http.MethodPost {
		http.Error(w, "Метод не поддерживается", http.StatusMethodNotAllowed)
		return
	}

	var requestData struct {
		Cashier string `json:"cashier"`
	}
	if err := json.NewDecoder(r.Body).Decode(&requestData); err != nil {
		http.Error(w, "Ошибка разбора JSON", http.StatusBadRequest)
		return
	}

	err := closeShift(requestData.Cashier)
	if err != nil {
		http.Error(w, fmt.Sprintf("Ошибка закрытия смены: %v", err), http.StatusInternalServerError)
		return
	}

	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]string{"status": "success", "message": "Смена успешно закрыта"})
}

func handleXReport(w http.ResponseWriter, r *http.Request) {
	// Устанавливаем CORS-заголовки
	w.Header().Set("Access-Control-Allow-Origin", "*")
	w.Header().Set("Access-Control-Allow-Methods", "POST, GET, OPTIONS, PUT, DELETE")
	w.Header().Set("Access-Control-Allow-Headers", "Accept, Content-Type, Content-Length, Accept-Encoding, X-CSRF-Token, Authorization")
	w.Header().Set("Access-Control-Allow-Private-Network", "true")

	// Обрабатываем предварительный запрос OPTIONS
	if r.Method == "OPTIONS" {
		w.WriteHeader(http.StatusOK)
		return
	}

	err := printXReport()
	if err != nil {
		http.Error(w, fmt.Sprintf("Ошибка печати X-отчета: %v", err), http.StatusInternalServerError)
		return
	}

	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]string{"status": "success", "message": "X-отчет успешно напечатан"})
}

func printCheck(checkData CheckData) error {
	var fptr *fptr10.IFptr
	var err error

	fptr, err = fptr10.NewSafe()
	if err != nil {
		return fmt.Errorf("ошибка инициализации драйвера ККТ: %v", err)
	}
	defer fptr.Destroy()

	// Подключение к кассе
	if ok, typepodkluch := connectWithKassa(fptr, *comport, *ipaddresskkt, *portkktatol, *ipaddressservrkkt); !ok {
		return fmt.Errorf("ошибка подключения к кассе: %v", typepodkluch)
	}
	defer fptr.Close()

	// Проверка открытия смены
	_, err = checkOpenShift(fptr, true, checkData.Employee)
	if err != nil {
		return fmt.Errorf("ошибка проверки/открытия смены: %v", err)
	}

	// Формирование JSON для печати чека
	checkJSON := formatCheckJSON(checkData)

	// Отправка команды печати чека
	result, err := sendComandeAndGetAnswerFromKKT(fptr, checkJSON)
	if err != nil {
		return fmt.Errorf("ошибка отправки команды печати чека: %v", err)
	}

	if !successCommand(result) {
		return fmt.Errorf("ошибка печати чека: %v", result)
	}

	return nil
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

	checkJSON := map[string]interface{}{
		"type":  "sell",
		"items": checkItems,
		"operator": map[string]string{
			"name": checkData.Employee,
		},
		"cashier": map[string]string{
			"name": checkData.Master,
		},
	}

	jsonBytes, _ := json.Marshal(checkJSON)
	return string(jsonBytes)
}

func sendComandeAndGetAnswerFromKKT(fptr *fptr10.IFptr, comJson string) (string, error) {
	var err error
	logsmy.LogginInFile("начало процедуры sendComandeAndGetAnswerFromKKT")
	//return "", nil
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
	getStatusKKTJson := "{\"type\": \"getDeviceStatus\"}"
	resgetStatusKKT, err := sendComandeAndGetAnswerFromKKT(fptr, getStatusKKTJson)
	if err != nil {
		errorDescr := fmt.Sprintf("ошибка (%v) получения статуса кассы", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
		return false, err
	}
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
	if err != nil {
		return fmt.Errorf("ошибка инициализации драйвера ККТ: %v", err)
	}
	defer fptr.Destroy()

	if ok, typepodkluch := connectWithKassa(fptr, *comport, *ipaddresskkt, *portkktatol, *ipaddressservrkkt); !ok {
		return fmt.Errorf("ошибка подключения к кассе: %v", typepodkluch)
	}
	defer fptr.Close()

	xReportJSON := `{"type": "reportX"}`
	result, err := sendComandeAndGetAnswerFromKKT(fptr, xReportJSON)
	if err != nil {
		return fmt.Errorf("ошибка отправки команды печати X-отчета: %v", err)
	}

	if !successCommand(result) {
		return fmt.Errorf("ошибка печати X-отчета: %v", result)
	}

	return nil
}
