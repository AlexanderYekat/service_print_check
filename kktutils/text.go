package kktutils

import (
	"fmt"
	consttypes "service_print_check/consttypes"
	fptr10 "service_print_check/fptr"
)

// PrintTextOnKKT печатает текст на ККТ через драйвер АТОЛ
func PrintTextOnKKT(fptr consttypes.IFptr10Interface, text string) error {
	// Устанавливаем текст как параметр
	fptr.SetParam(fptr10.LIBFPTR_PARAM_TEXT, text)
	
	// Вызываем метод печати текста
	err := fptr.PrintText()
	
	if err != nil {
		return fmt.Errorf("ошибка печати текста на ККТ: %v", err)
	}
	
	return nil
}

// PrintText печатает текст на ККТ
func PrintText(fptr consttypes.IFptr10Interface, text string) error {
	return PrintTextOnKKT(fptr, text)
}
