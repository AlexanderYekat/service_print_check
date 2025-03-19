package websocket

import (
	"fmt"
	consttypes "service_print_check/consttypes"
	logsmy "service_print_check/packetlog"
)

// PrinterImpl реализует интерфейс TAbstractPrinter
type PrinterImpl struct {
	handler *Handler
}

// PrintXReport печатает X-отчет
func (p *PrinterImpl) PrintXReport(fptr consttypes.IFptr10Interface) error {
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("подключение к кассе")
	if ok, typepodkluch := p.handler.connectWithKassa(fptr, *p.handler.comport, *p.handler.ipaddresskkt, *p.handler.portkktatol, *p.handler.ipaddressservrkkt); !ok {
		if !*p.handler.emulation {
			return fmt.Errorf("ошибка подключения к кассе: %v", typepodkluch)
		}
	}
	defer fptr.Close()

	xReportJSON := `{"type": "reportX"}`
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("отправка команды печати X-отчета")
	result, err := p.handler.sendComandeAndGetAnswerFromKKT(fptr, xReportJSON)
	if err != nil {
		return fmt.Errorf("ошибка отправки команды печати X-отчета: %v", err)
	}

	if !p.handler.successCommand(result) {
		return fmt.Errorf("ошибка печати X-отчета: %v", result)
	}

	return nil
}
