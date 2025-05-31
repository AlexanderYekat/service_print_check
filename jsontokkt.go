package main

import (
	"fmt"
	"net/http"
	handlers "service_print_check/handlers"
	"service_print_check/kktutils"
	"service_print_check/settings"

	"github.com/rs/cors"
)

const Version_of_program = "2025_05_31_01"

var glFptrDriver kktutils.TFptr10Driver
var currentSettings settings.TSettings

func runServer() error {
	var err error
	// Инициализируем драйвер ККТ
	err = glFptrDriver.NewSafe()
	if err != nil {
		fmt.Printf("Ошибка при инициализации драйвера ККТ: %v", err)
		//logsmy.Logsmap[consttypes.LOGERROR].Printf("Ошибка при инициализации драйвера ККТ: %v", err)
		return err
	}

	fetchHandler := handlers.NewHandler(
		&currentSettings.ComKKT,
		&currentSettings.IpKKT,
		&currentSettings.PortKKT,
		&currentSettings.IpServKKT,
		&currentSettings.Emulation,
		glFptrDriver.GetFptr10(), // Передаем инициализированный драйвер
		Version_of_program,       // Передаем версию программы
	)

	mux := http.NewServeMux()
	mux.HandleFunc("/api/print-check", fetchHandler.HandlePrintCheck)
	mux.HandleFunc("/api/close-shift", fetchHandler.HandleCloseShift)

	c := cors.New(cors.Options{
		AllowedOrigins:      []string{"http://localhost:8081", "http://localhost", "null"},
		AllowedMethods:      []string{"POST", "OPTIONS"},
		AllowedHeaders:      []string{"content-type", "access-control-request-private-network"},
		AllowPrivateNetwork: true, // Добавляем это
	})
	handler := c.Handler(mux)
	addr := ":8081"
	fmt.Printf("Сервер запущен на %v\n", addr)
	err = http.ListenAndServe(addr, handler)
	if err != nil {
		fmt.Printf("Ошибка при запуске HTTP сервера: %v", err)
		return err
	}

	return nil
}

func main() {
	fmt.Println("запускаем как обычное приложение")
	err := runServer()
	fmt.Println("err=", err)
}
