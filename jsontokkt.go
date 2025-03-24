package main

import (
	"encoding/json"
	"flag"
	"fmt"
	"io/ioutil"
	"log"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"service_print_check/consttypes"
	"service_print_check/kktutils"
	"service_print_check/models"
	logsmy "service_print_check/packetlog"
	mywebsocket "service_print_check/websocket"

	"time"

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

const Version_of_program = "2025_03_24_09"

var glFptrDriver kktutils.TFptr10Driver

type myService struct{}

func (m *myService) Execute(args []string, r <-chan svc.ChangeRequest, changes chan<- svc.Status) (ssec bool, errno uint32) {
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

	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Начало инициализации")
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
				logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Освобождение ресурсов драйвера ККТ")
				glFptrDriver.Destroy()
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
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Запуск службы CloudPosBridge, версия:", Version_of_program)

	elog, err := eventlog.Open("CloudPosBridge")
	if err != nil {
		logsmy.Logsmap[consttypes.LOGERROR].Printf("Не удалось открыть журнал событий: %v", err)
		return err
	}
	defer elog.Close()

	addr := ":8081"
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("Попытка запуска сервера на %s", addr)

	mux := http.NewServeMux()

	// Инициализируем драйвер ККТ
	err = glFptrDriver.NewSafe()
	if err != nil {
		logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при инициализации драйвера ККТ: %v", err)
		return err
	}

	wsHandler := mywebsocket.NewHandler(
		comport,
		ipaddresskkt,
		portkktatol,
		ipaddressservrkkt,
		emulation,
		glFptrDriver.GetFptr10(), // Передаем инициализированный драйвер
		Version_of_program,       // Передаем версию программы
	)
	mux.HandleFunc("/ws", wsHandler.HandleWebSocket)

	server := &http.Server{
		Addr:    addr,
		Handler: mux,
	}

	err = server.ListenAndServe()
	if err != nil {
		logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при запуске сервера: %v", err)
		return err
	}

	return nil
}

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

var currentSettings models.Settings

func getSettingsHandler(w http.ResponseWriter, r *http.Request) {
	json.NewEncoder(w).Encode(currentSettings)
}

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

	*clearLogsProgramm = currentSettings.ClearLogs
	*LogsDebugs = currentSettings.Debug
	*comport = currentSettings.Com
	*CassirName = currentSettings.Cassir
	*ipaddresskkt = currentSettings.IpKKT
	*portkktatol = currentSettings.PortKKT
	*ipaddressservrkkt = currentSettings.IpServKKT
	*emulation = currentSettings.Emulation
	*allowedOrigin = currentSettings.AllowedOrigin

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
		cmd = exec.Command("net", "stop", serviceName)
		err := cmd.Run()
		if err != nil {
			logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при остановке службы: %v", err)
			w.WriteHeader(http.StatusInternalServerError)
			json.NewEncoder(w).Encode(map[string]string{"status": "error", "message": err.Error()})
			return
		}

		cmd = exec.Command("net", "start", serviceName)
		err = cmd.Run()
		if err != nil {
			logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при запуске службы: %v", err)
			w.WriteHeader(http.StatusInternalServerError)
			json.NewEncoder(w).Encode(map[string]string{"status": "error", "message": err.Error()})
			return
		}
	} else {
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
	isService, err := svc.IsWindowsService()
	if err != nil {
		logsmy.Logsmap[consttypes.LOGERROR].Fatalf("не удалось определить, запущена ли программа как служба: %v", err)
	}
	if isService {
		fmt.Println("запускаем службу")
		runService(false)
		return
	}

	fmt.Println("запускаем как обычное приложение")
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
	http.HandleFunc("/api/restart", enableCORS(restartServiceHandler))
	http.HandleFunc("/api/logpath", enableCORS(getLogPathHandler))
	http.HandleFunc("/api/openlogs", enableCORS(openLogsHandler))

	fs := http.FileServer(http.Dir("static"))
	http.Handle("/static/", http.StripPrefix("/static/", fs))

	fsjs := http.FileServer(http.Dir("static/js"))
	http.Handle("/static/js/", http.StripPrefix("/static/js/", fsjs))

	fscss := http.FileServer(http.Dir("static/css"))
	http.Handle("/static/css/", http.StripPrefix("/static/css/", fscss))

	go func() {
		logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Запуск веб-сервера на http://localhost:8080")
		if err := http.ListenAndServe(":8080", nil); err != nil {
			logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка запуска веб-сервера: %v", err)
		}
	}()

	time.Sleep(100 * time.Millisecond)

	url := "http://localhost:8080/settings"
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("Открытие страницы настроек в браузере: %s", url)
	if err := openBrowser(url); err != nil {
		logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при открытии браузера: %v", err)
	}

	_, bErr := svc.IsWindowsService()
	if bErr == nil {
		fmt.Println("IsWindowsService()")
	}
	logsmy.LogginInFile("Начало работы программы")
	logsmy.LogginInFile(fmt.Sprintf("путь исполняемого файла: %v", execPath))
	logsmy.LogginInFile(fmt.Sprintf("Версия программы: %v", Version_of_program))

	// Инициализируем драйвер ККТ через kktutils
	glFptrDriver.NewSafe()
	if err != nil {
		logsmy.Logsmap[consttypes.LOGERROR].Fatalf("Не удалось инициализировать драйвер ККТ: %v", err)
		return
	} else {
		defer glFptrDriver.Destroy() // Освобождаем ресурсы при завершении программы
		logsmy.LogginInFile(fmt.Sprintf("версия драйвера: %v", glFptrDriver.Version()))
	}

	isService, err = svc.IsWindowsService()
	if err != nil {
		logsmy.Logsmap[consttypes.LOGERROR].Fatalf("не удалось определить, запущена ли программа как служба: %v", err)
	}
	if isService {
		fmt.Println("запускаем службу")
		runService(false)
		return
	}

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
		allowedOrigins := []string{
			"http://localhost:8080",
			"http://localhost",
			"http://localhost:8081",
			"localhost:8081",
		}

		origin := r.Header.Get("Origin")
		for _, allowed := range allowedOrigins {
			if origin == allowed {
				w.Header().Set("Access-Control-Allow-Origin", origin)
				break
			}
		}

		w.Header().Set("Access-Control-Allow-Methods", "POST, OPTIONS")
		w.Header().Set("Access-Control-Allow-Headers", "Content-Type, Access-Control-Allow-Private-Network")

		if r.Method == "OPTIONS" {
			w.WriteHeader(http.StatusOK)
			return
		}

		next.ServeHTTP(w, r)
	}
}
