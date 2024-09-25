package handlers

import (
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"time"

	"service_print_check/internal/config"
	"service_print_check/internal/kkt"

	"github.com/gorilla/handlers"
)

func StartServer() error {
	http.HandleFunc("/api/close-shift", handleCloseShift)

	// Обработка статических файлов
	fs := http.FileServer(http.Dir("static"))
	http.Handle("/static/", http.StripPrefix("/static/", fs))

	// Настройки CORS
	allowedOrigins := handlers.AllowedOrigins([]string{config.Current.AllowedOrigin})
	allowedMethods := handlers.AllowedMethods([]string{"GET", "POST", "OPTIONS"})
	allowedHeaders := handlers.AllowedHeaders([]string{"Content-Type", "Access-Control-Allow-Private-Network"})

	server := &http.Server{
		Addr:         ":8080",
		Handler:      handlers.CORS(allowedOrigins, allowedMethods, allowedHeaders)(http.DefaultServeMux),
		ReadTimeout:  5 * time.Second,
		WriteTimeout: 10 * time.Second,
		IdleTimeout:  120 * time.Second,
	}

	log.Println("Сервер запущен на :8080")
	if err := server.ListenAndServe(); err != nil && err != http.ErrServerClosed {
		log.Fatalf("Ошибка запуска веб-сервера: %v", err)
		return err
	}
	return nil
}

func RunServerWithRetry(maxRetries int, retryInterval time.Duration) error {
	var err error
	for i := 0; i < maxRetries; i++ {
		err = StartServer()
		if err == nil {
			return nil
		}
		time.Sleep(retryInterval)
	}
	return fmt.Errorf("не удалось запустить сервер после %d попыток: %v", maxRetries, err)
}

func handleCloseShift(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
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

	if err := kkt.CloseShift(ctx, requestData.Cashier); err != nil {
		http.Error(w, fmt.Sprintf("Ошибка закрытия смены: %v", err), http.StatusInternalServerError)
		return
	}

	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]string{"status": "success", "message": "Смена успешно закрыта"})
}

// Реализация обработчиков...
