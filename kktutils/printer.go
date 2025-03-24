package kktutils

import (
	"fmt"
	"service_print_check/consttypes"
	logsmy "service_print_check/packetlog"
)

// IAbstractPrinter представляет интерфейс для печати отчетов и слипов
type IAbstractPrinter interface {
	PrintXReport(fptr consttypes.IFptr10Interface) error
	PrintSlip(fptr consttypes.IFptr10Interface, slip string) error
	PrintText(fptr consttypes.IFptr10Interface, text string) error
}

// PrinterImpl предоставляет реализацию IAbstractPrinter
type PrinterImpl struct {
	Comport           *int
	Ipaddresskkt      *string
	Portkktatol       *int
	Ipaddressservrkkt *string
	Emulation         *bool
}

// PrintXReport печатает X-отчет используя функции kktutils
func (p *PrinterImpl) PrintXReport(fptr consttypes.IFptr10Interface) error {
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("подключение к кассе")
	if ok, typepodkluch := ConnectWithKassa(fptr, *p.Comport, *p.Ipaddresskkt, *p.Portkktatol, *p.Ipaddressservrkkt); !ok {
		if !*p.Emulation {
			return fmt.Errorf("ошибка подключения к кассе: %v", typepodkluch)
		}
	}
	defer fptr.Close()

	// Используем общую функцию для X-отчета
	xReportJSON := FormatXReportJSON()

	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("отправка команды печати X-отчета")
	result, err := SendCommandAndGetAnswerFromKKT(fptr, xReportJSON, *p.Emulation)
	if err != nil {
		return fmt.Errorf("ошибка отправки команды печати X-отчета: %v", err)
	}

	if !SuccessCommand(result) {
		return fmt.Errorf("ошибка печати X-отчета: %v", result)
	}

	return nil
}

// PrintSlip печатает банковский слип
func (p *PrinterImpl) PrintSlip(fptr consttypes.IFptr10Interface, slip string) error {
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("подключение к кассе")
	if ok, typepodkluch := ConnectWithKassa(fptr, *p.Comport, *p.Ipaddresskkt, *p.Portkktatol, *p.Ipaddressservrkkt); !ok {
		if !*p.Emulation {
			return fmt.Errorf("ошибка подключения к кассе: %v", typepodkluch)
		}
	}
	defer fptr.Close()

	return PrintSlipDefault(fptr, slip, *p.Emulation)
}

// PrintText печатает текст на ККТ
func (p *PrinterImpl) PrintText(fptr consttypes.IFptr10Interface, text string) error {
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("подключение к кассе")
	if ok, typepodkluch := ConnectWithKassa(fptr, *p.Comport, *p.Ipaddresskkt, *p.Portkktatol, *p.Ipaddressservrkkt); !ok {
		if !*p.Emulation {
			return fmt.Errorf("ошибка подключения к кассе: %v", typepodkluch)
		}
	}
	defer fptr.Close()

	return PrintText(fptr, text)
}

// NewPrinter создает новый экземпляр PrinterImpl
func NewPrinter(comport *int, ipaddresskkt *string, portkktatol *int, ipaddressservrkkt *string, emulation *bool) IAbstractPrinter {
	return &PrinterImpl{
		Comport:           comport,
		Ipaddresskkt:      ipaddresskkt,
		Portkktatol:       portkktatol,
		Ipaddressservrkkt: ipaddressservrkkt,
		Emulation:         emulation,
	}
}
