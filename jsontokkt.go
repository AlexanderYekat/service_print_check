//go:generate ./resource/goversioninfo.exe -icon=resource/icon.ico -manifest=resource/goversioninfo.exe.manifest
package main

import (
	"encoding/json"
	"flag"
	"fmt"
	"io/ioutil"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	fptr10 "service_print_check/fptr"
	"strconv"
	"strings"
	"time"

	"github.com/rs/cors"
	"golang.org/x/sys/windows/svc"
	"golang.org/x/sys/windows/svc/debug"
)

var comport = flag.Int("com", 0, "ком порт кассы")
var CassirName = flag.String("cassir", "", "имя кассира")
var ipaddresskkt = flag.String("ipkkt", "", "ip адрес ккт")
var portkktatol = flag.Int("portipkkt", 0, "порт ip ккт")
var ipaddressservrkkt = flag.String("ipservkkt", "", "ip адрес сервера ккт")
var allowedOrigin = flag.String("allowedOrigin", "", "разрешенный origin для WebSocket соединений")

const Version_of_program = "2024_09_21_01"

type myService struct{}

func (m *myService) Execute(args []string, r <-chan svc.ChangeRequest, changes chan<- svc.Status) (ssec bool, errno uint32) {
	const cmdsAccepted = svc.AcceptStop | svc.AcceptShutdown
	changes <- svc.Status{State: svc.StartPending}

	changes <- svc.Status{State: svc.Running, Accepts: cmdsAccepted}

	serverErrChan := make(chan error, 1)
	go func() {
		if err := runServerWithRetry(5, 10*time.Second); err != nil {
			serverErrChan <- err
		}
	}()
	for {
		select {
		case c := <-r:
			switch c.Cmd {
			case svc.Interrogate:
				changes <- c.CurrentStatus
			case svc.Stop, svc.Shutdown:
				return
			default:
				fmt.Println("получили неизвестную команду")
			}
		case err := <-serverErrChan:
			fmt.Println("ошибка запуска сервера:", err)
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
		time.Sleep(retryInterval)
	}
	return fmt.Errorf("не удалось запустить сервер после %d попыток: %v", maxRetries, err)
}

func runServer() error {
	var err error
	addr := ":8081"

	mux := http.NewServeMux()
	mux.HandleFunc("/api/close-shift", handleCloseShift)
	// Настройка CORS
	c := cors.New(cors.Options{
		AllowedOrigins:      []string{"https://188.225.31.209:8443"},
		AllowedMethods:      []string{"POST", "OPTIONS"},
		AllowedHeaders:      []string{"content-type", "access-control-request-private-network"},
		AllowPrivateNetwork: true, // Добавляем это
		//Debug:               true,
		//AllowOriginRequestFunc: func(r *http.Request, origin string) bool {
		//	fmt.Println("********************origin******************", origin)
		//	return origin == "https://188.225.31.209:8443"
		//},
	})
	handler := c.Handler(mux)

	fmt.Printf("Сервер запущен на %v\n", addr)
	err = http.ListenAndServe(addr, handler)
	if err != nil {
		return err
	}
	return nil
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

func sendComandeAndGetAnswerFromKKT(fptr *fptr10.IFptr, comJson string) (string, error) {
	fptr.SetParam(fptr10.LIBFPTR_PARAM_JSON_DATA, comJson)
	result := fptr.GetParamString(fptr10.LIBFPTR_PARAM_JSON_DATA)
	if strings.Contains(result, "Нет связи") {
		if ok, typepodkluch := connectWithKassa(fptr, *comport, *ipaddresskkt, *portkktatol, *ipaddressservrkkt); !ok {
			descrErr := fmt.Sprintf("ошибка соединения с кассовым аппаратом %v", typepodkluch)
			fmt.Println(descrErr)
		}
	}
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

func closeShift(cashier string) error {
	fptr, err := fptr10.NewSafe()
	if err != nil {
		return fmt.Errorf("ошибка инициализации драйвера ККТ: %v", err)
	}
	defer func() {
		if fptr != nil {
			fptr.Destroy()
		}
	}()

	if ok, typepodkluch := connectWithKassa(fptr, *comport, *ipaddresskkt, *portkktatol, *ipaddressservrkkt); !ok {
		return fmt.Errorf("ошибка подключения к кассе: %v", typepodkluch)
	}
	defer func() {
		if fptr != nil {
			fptr.Close()
		}
	}()

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
	*comport = currentSettings.Com
	*CassirName = currentSettings.Cassir
	*ipaddresskkt = currentSettings.IpKKT
	*portkktatol = currentSettings.PortKKT
	*ipaddressservrkkt = currentSettings.IpServKKT
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

	// Останавливаем службу
	cmd = exec.Command("net", "stop", serviceName)
	err := cmd.Run()
	if err != nil {
		fmt.Println("Ошибка при остановке службы:", err)
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"status": "error", "message": err.Error()})
		return
	}

	// Запускаем службу
	cmd = exec.Command("net", "start", serviceName)
	err = cmd.Run()
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"status": "error", "message": err.Error()})
		return
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
	isService, err := svc.IsWindowsService()
	if err != nil {
		fmt.Println("не удалось определить, запущена ли программа как служба:", err)
	}
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
		if err := http.ListenAndServe(":8080", nil); err != nil {
			fmt.Println("Ошибка запуска веб-сервера:", err)
		}
	}()

	// Даем серверу время на запуск
	time.Sleep(100 * time.Millisecond)

	// Открываем страницу настроек в браузере
	url := "http://localhost:8080/settings"
	if err := openBrowser(url); err != nil {
		fmt.Println("Ошибка при открытии браузера:", err)
	}

	// Держим приложение запущенным
	select {}
}

func runService(isDebug bool) {
	run := svc.Run
	if isDebug {
		run = debug.Run
	}
	err := run("CloudPosBridge", &myService{})
	if err != nil {
		return
	}
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
