package printer

import (
	"fmt"
	"service_print_check/internal/kkt"
)

type Service struct {
	kktService *kkt.Service
}

func NewService(kktService *kkt.Service) *Service {
	return &Service{kktService: kktService}
}

// func (s *Service) CloseShift(data models.TCheckData) error {
func (s *Service) CloseShift(cashier string) error {
	// Реализация печати чека
	err := s.kktService.CloseShift(cashier)
	if err != nil {
		return fmt.Errorf("ошибка закрытия смены: %w", err)
	}
	return nil
}
