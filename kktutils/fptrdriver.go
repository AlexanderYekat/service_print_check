package kktutils

import (
	"service_print_check/consttypes"
	fptr10 "service_print_check/fptr"
)

// TFptr10Driver предоставляет обертку для работы с драйвером ККТ
type TFptr10Driver struct {
	fptr consttypes.IFptr10Interface
}

// NewSafe создает новый экземпляр драйвера ККТ или использует переданный
func (driver TFptr10Driver) NewSafe() error {
	var err error
	if driver.fptr == nil {
		driver.fptr, err = fptr10.NewSafe()
		if err != nil {
			return err
		}
	}
	return nil
}

// Open открывает соединение с ККТ
func (driver TFptr10Driver) Open() error {
	return driver.fptr.Open()
}

// IsOpened проверяет, открыто ли соединение с ККТ
func (driver TFptr10Driver) IsOpened() bool {
	return driver.fptr.IsOpened()
}

// ApplySingleSettings применяет настройки к ККТ
func (driver TFptr10Driver) ApplySingleSettings() error {
	return driver.fptr.ApplySingleSettings()
}

// Close закрывает соединение с ККТ
func (driver TFptr10Driver) Close() {
	driver.fptr.Close()
}

func (driver TFptr10Driver) Version() string {
	return driver.fptr.Version()
}

func (driver TFptr10Driver) GetFptr10() consttypes.IFptr10Interface {
	return driver.fptr
}

// Destroy освобождает ресурсы драйвера ККТ
func (driver TFptr10Driver) Destroy() {
	driver.fptr.Destroy()
}
