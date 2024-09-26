package kkt

import (
	"fmt"
	"service_print_check/internal/driver"
)

type Service struct {
	fptrDriver *driver.FptrDriver
}

func NewService() (*Service, error) {
	fptrDriverLoc, err := driver.NewFptrDriver()
	if err != nil {
		return nil, err
	}
	//driverFptr := fptrDriver.FptrInstance
	return &Service{fptrDriver: fptrDriverLoc}, nil
}

func (s *Service) CloseShift(cashier string) error {
	if !s.fptrDriver.IsOpened() {
		if err := s.fptrDriver.Open(); err != nil {
			return fmt.Errorf("ошибка подключение к кассе: %v", err)
		}
	}
	defer func() {
		if s.fptrDriver != nil {
			s.fptrDriver.Close()
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

func (s *Service) Free() error {
	if s.fptrDriver == nil {
		return fmt.Errorf("ошибка освобождения ресурсов: драйвер не инициализирован")
	}
	s.fptrDriver.Destroy()
	return nil
}

// Методы для работы с ККТ
