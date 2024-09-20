//go:generate ./resource/goversioninfo.exe -icon=resource/icon.ico -manifest=resource/goversioninfo.exe.manifest
package main

import (
	"bufio"
	consttypes "checkservice/consttypes"
	logsmy "checkservice/packetlog"
	merc "checkservice/sendtcp"
	"crypto/tls"
	"encoding/json"
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
var emulation = flag.Bool("emul", false, "эмуляция")
var dontprintrealfortest = flag.Bool("test", false, "тест - не печатать реальный чек")
var emulatmistakes = flag.Bool("emulmist", false, "эмуляция ошибок")
var emulatmistakesOpenCheck = flag.Bool("emulmistopencheck", false, "эмуляция ошибок открытия чека")
var useHTTPS = flag.Bool("https", false, "Использовать HTTPS (true) или HTTP (false)")

const Version_of_program = "2024_09_17_01"

func main() {
	var err error
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
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Тип кассы меркурий")

	mux := http.NewServeMux()
	mux.HandleFunc("/api/print-check", handlePrintCheck)
	mux.HandleFunc("/api/close-shift", handleCloseShift)
	mux.HandleFunc("/api/x-report", handleXReport)
	mux.HandleFunc("/", handlermainhtml)

	// Настройка CORS
	c := cors.New(cors.Options{
		//AllowedOrigins: []string{"http://localhost:8080"}, // Разрешаем все источники
		//AllowedOrigins: []string{"http://localhost:8080", "http://188.225.31.209:8080"},
		//AllowedOrigins: []string{"http://188.225.31.209:8443"},
		AllowedOrigins: []string{"https://localhost:8080"},
		//AllowedOrigins: []string{"http://127.0.0.1:8080"},
		AllowedMethods: []string{"POST", "OPTIONS", "GET"},
		//AllowedHeaders: []string{"content-type", "access-control-request-private-network"},
		AllowedHeaders: []string{"content-type", "Access-Control-Allow-Private-Network"},
		//AllowedHeaders: []string{"Access-Control-Allow-Private-Network"},
		//AllowCredentials:    true,
		AllowPrivateNetwork: true, // Добавляем это
	})

	handler := c.Handler(mux)

	if *useHTTPS {
		// Настройка HTTPS
		cert, err := tls.LoadX509KeyPair("server.crt", "server.key")
		if err != nil {
			log.Fatal(err)
		}

		tlsConfig := &tls.Config{
			Certificates: []tls.Certificate{cert},
		}

		server := &http.Server{
			Addr:      "0.0.0.0:8443",
			Handler:   handler,
			TLSConfig: tlsConfig,
		}

		fmt.Println("Сервер запущен на https://127.0.0.1:8443")
		log.Fatal(server.ListenAndServeTLS("", ""))
	} else {
		// Запуск HTTP сервера
		fmt.Println("Сервер запущен на http://127.0.0.1:8085")
		log.Fatal(http.ListenAndServe(":8085", handler))
	}
} //main

func handlermainhtml(w http.ResponseWriter, r *http.Request) {
	fmt.Fprintf(w, "Hello, HTTPS world!")
}

func handlePrintCheck(w http.ResponseWriter, r *http.Request) {
	fmt.Println("handlePrintCheck вызван")
	fmt.Println("Метод запроса:", r.Method)
	fmt.Println("URL запроса:", r.URL)
	origin := r.Header.Get("Origin")
	fmt.Println("Origin:", origin)
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

	// Обрабатываем предварительный запрос OPTIONS
	if r.Method == "OPTIONS" {
		w.Header().Set("Access-Control-Allow-Methods", "GET, OPTIONS")
		w.Header().Set("Access-Control-Allow-Headers", "access-control-request-private-network, content-type")
		w.Header().Set("Access-Control-Allow-Origin", "http://127.0.0.1:8080")

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

	var checkData consttypes.TCheckData
	err = json.Unmarshal(body, &checkData)
	if err != nil {
		http.Error(w, "Ошибка разбора JSON", http.StatusBadRequest)
		return
	}

	// Здесь вызываем функцию для печати чека
	var checkNumber string
	// Использование в main
	connectionParams := merc.NewDefaultConnectionParams()
	emulationParams := consttypes.NewDefaultEmulationParams()
	authParams := merc.NewDefaultAuthParams()
	checkNumber, err = merc.PrintCheck(connectionParams,
		checkData,
		&emulationParams,
		&authParams,
	)
	if err != nil {
		errorResponse := consttypes.TPrintCheckResponse{
			Status:      "error",
			Message:     fmt.Sprintf("Ошибка печати чека: %v", err),
			CheckNumber: "", // или -1, если хотите обозначить отсутствие номера чека
		}

		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(errorResponse)
		return
	}

	response := consttypes.TPrintCheckResponse{
		Status:      "success",
		Message:     "Чек успешно напечатан",
		CheckNumber: checkNumber,
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(response)
	json.NewEncoder(w).Encode(map[string]string{"status": "success", "message": "Чек успешно напечатан"})
} //handlePrintCheck

func handleCloseShift(w http.ResponseWriter, r *http.Request) {
	// Устанавливаем CORS-заголовки
	w.Header().Set("Access-Control-Allow-Methods", "POST, GET, OPTIONS, PUT, DELETE")
	w.Header().Set("Access-Control-Allow-Headers", "Accept, Content-Type, Content-Length, Accept-Encoding, X-CSRF-Token, Authorization")
	w.Header().Set("Access-Control-Allow-Origin", "http://188.225.31.209:8080")
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

	shiftNum, err := closeShift(requestData.Cashier)
	if err != nil {
		http.Error(w, fmt.Sprintf("Ошибка закрытия смены: %v", err), http.StatusInternalServerError)
		return
	}

	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]string{"status": "success", "message": "Смена успешно закрыта", "shiftNum": strconv.Itoa(shiftNum)})
}

func handleXReport(w http.ResponseWriter, r *http.Request) {
	// Устанавливаем CORS-заголовки
	fmt.Println("handleXReport вызван")
	origin := r.Header.Get("Origin")
	fmt.Println("Origin:", origin)
	w.Header().Set("Access-Control-Allow-Methods", "POST, GET, OPTIONS, PUT, DELETE")
	w.Header().Set("Access-Control-Allow-Headers", "Accept, Content-Type, Content-Length, Accept-Encoding, X-CSRF-Token, Authorization")
	w.Header().Set("Access-Control-Allow-Private-Network", "true")
	w.Header().Set("Access-Control-Allow-Origin", "http://188.225.31.209:8080")

	// Обрабатываем предварительный запрос OPTIONS
	if r.Method == "OPTIONS" {
		fmt.Println("Получен OPTIONS запрос")
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

func successCommand(resulJson string) bool {
	res := true
	indOsh := strings.Contains(resulJson, "ошибка")
	indErr := strings.Contains(resulJson, "error")
	if indErr || indOsh {
		res = false
	}
	return res
} //successCommand

func closeShift(cashier string) (int, error) {
	connectionParams := merc.NewDefaultConnectionParams()
	emulationParams := consttypes.NewDefaultEmulationParams()
	authParams := merc.NewDefaultAuthParams()
	result, err := merc.CloseShift(connectionParams,
		cashier,
		&authParams,
		&emulationParams,
	)
	if err != nil {
		logsmy.Logsmap[consttypes.LOGERROR].Println(fmt.Sprintf("ошибка отправки команды закрытия смены: %v", err))
		return 0, fmt.Errorf("ошибка отправки команды закрытия смены: %v", err)
	}

	if result.Result != 0 {
		logsmy.Logsmap[consttypes.LOGERROR].Println(fmt.Sprintf("ошибка закрытия смены: %v", result.Description))
		return 0, fmt.Errorf("ошибка закрытия смены: %v", result.Description)
	}

	return result.ShiftNum, nil
}

func printXReport() error {
	connectionParams := merc.NewDefaultConnectionParams()
	emulationParams := consttypes.NewDefaultEmulationParams()
	authParams := merc.NewDefaultAuthParams()
	result, err := merc.PrintXReport(connectionParams,
		&authParams,
		&emulationParams,
	)
	if err != nil {
		logsmy.Logsmap[consttypes.LOGERROR].Println(fmt.Sprintf("ошибка печати X-отчета: %v", err))
		return fmt.Errorf("ошибка печати X-отчета: %v", err)
	}

	if result.Result != 0 {
		logsmy.Logsmap[consttypes.LOGERROR].Println(fmt.Sprintf("ошибка печати X-отчета: %v", result.Description))
		return fmt.Errorf("ошибка печати X-отчета: %v", result.Description)
	}
	return nil
} //printXReport
