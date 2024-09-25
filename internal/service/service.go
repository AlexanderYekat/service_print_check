package service

import (
	"log"
	"time"

	"service_print_check/internal/handlers"

	"golang.org/x/sys/windows/svc"
	"golang.org/x/sys/windows/svc/debug"
)

type myService struct{}

func (m *myService) Execute(args []string, r <-chan svc.ChangeRequest, changes chan<- svc.Status) (bool, uint32) {
	const cmdsAccepted = svc.AcceptStop | svc.AcceptShutdown
	changes <- svc.Status{State: svc.StartPending}

	changes <- svc.Status{State: svc.Running, Accepts: cmdsAccepted}

	serverErrChan := make(chan error, 1)
	go func() {
		if err := handlers.RunServerWithRetry(5, 10*time.Second); err != nil {
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
				return false, 0
			default:
				log.Println("Получена неизвестная команда")
			}
		case err := <-serverErrChan:
			log.Println("Ошибка запуска сервера:", err)
			return false, 1
		}
	}
}

func Run(isDebug bool) {
	run := svc.Run
	if isDebug {
		run = debug.Run
	}
	err := run("CloudPosBridge", &myService{})
	if err != nil {
		log.Fatalf("Ошибка запуска службы: %v", err)
	}
}
