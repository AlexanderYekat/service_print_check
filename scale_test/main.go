package main

import (
	"flag"
	"fmt"
	"log"
	"os"
	"time"

	"service_print_check/equipment"
)

func main() {
	// Параметр коммуникационного порта
	comPort := flag.Int("port", 5, "COM port number to use (default: 5)")
	attempts := flag.Int("attempts", 1, "Number of attempts to connect (default: 1)")
	delay := flag.Int("delay", 1, "Delay between attempts in seconds (default: 1)")
	flag.Parse()

	fmt.Println("=== Программа тестирования весов ===\n")
	fmt.Printf("Настройки:\n- COM-порт: %d\n- Количество попыток: %d\n- Задержка между попытками: %d сек.\n\n", *comPort, *attempts, *delay)

	var lastError error
	for i := 0; i < *attempts; i++ {
		if i > 0 {
			fmt.Printf("\nПопытка %d из %d (пауза %d сек.)...\n", i+1, *attempts, *delay)
			time.Sleep(time.Duration(*delay) * time.Second)
		} else {
			fmt.Printf("Попытка %d из %d...\n", i+1, *attempts)
		}

		// Пытаемся получить вес с устройства
		weight, err := equipment.GetWeight(*comPort)
		if err != nil {
			lastError = err
			log.Printf("❌ Ошибка получения веса: %v\n", err)
			continue
		}

		// Успешно получили вес
		fmt.Printf("✅ Вес успешно получен: %s\n", weight)
		os.Exit(0)
	}

	// Если все попытки не удались
	fmt.Println("\n❌ Все попытки получения веса завершились с ошибками.")
	fmt.Println("\nРекомендации по устранению проблемы:")
	fmt.Println("1. Проверьте, подключены ли весы к указанному COM-порту.")
	fmt.Println("2. Убедитесь, что устройство включено и работает.")
	fmt.Println("3. Проверьте правильность параметров COM-порта (скорость передачи и т.д.).")
	fmt.Println("4. Попробуйте запустить программу от имени администратора.")
	fmt.Printf("\nПоследняя ошибка: %v\n", lastError)
	os.Exit(1)
}
