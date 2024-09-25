package main

import (
	"log"
	"os"

	"service_print_check/internal/config"
	"service_print_check/internal/handlers"
	"service_print_check/internal/service"

	"golang.org/x/sys/windows/svc"
)

func main() {
	execPath, err := os.Executable()
	if err != nil {
		log.Fatalf("Ошибка получения пути исполняемого файла: %v", err)
	}
	log.Printf("Путь исполняемого файла: %s", execPath)

	log.Println("Начало работы программы")

	isService, err := svc.IsWindowsService()
	if err != nil {
		log.Fatalf("Не удалось определить, запущена ли программа как служба: %v", err)
	}

	if isService {
		log.Println("Запуск службы")
		service.Run(false)
		return
	}

	log.Println("Запуск как обычное приложение")
	config.Init()          // Инициализируем настройки
	handlers.StartServer() // Запускаем веб-сервер
}
