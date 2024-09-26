// fptr_driver.go
package driver

import (
	"fmt"
	fptr10 "service_print_check/internal/fptr"
)

type FptrDriver struct {
	fptrInstance IFptr10Interface
}

func NewFptrDriver() (*FptrDriver, error) {
	// Инициализация драйвера
	fptrInstance, err := fptr10.NewSafe()
	if err != nil {
		return nil, fmt.Errorf("ошибка инициализации драйвера ККТ: %v", err)
	}
	return &FptrDriver{fptrInstance: fptrInstance}, nil
}

func (f *FptrDriver) Open() error {
	return f.fptrInstance.Open()
}

func (f *FptrDriver) IsOpened() bool {
	return f.fptrInstance.IsOpened()
}

func (f *FptrDriver) Close() error {
	return f.fptrInstance.Close()
}

func (f *FptrDriver) Destroy() {
	f.fptrInstance.Destroy()
}

// Реализация других методов интерфейса
