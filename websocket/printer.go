package websocket

import (
	"encoding/json"
	"fmt"
	consttypes "service_print_check/consttypes"
	fptr10 "service_print_check/fptr"
	logsmy "service_print_check/packetlog"
	"strings"
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

// PrintSlip печатает банковский слип
func (p *PrinterImpl) PrintSlip(fptr consttypes.IFptr10Interface, slip string) error {
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("подключение к кассе")
	if ok, typepodkluch := p.handler.connectWithKassa(fptr, *p.handler.comport, *p.handler.ipaddresskkt, *p.handler.portkktatol, *p.handler.ipaddressservrkkt); !ok {
		if !*p.handler.emulation {
			return fmt.Errorf("ошибка подключения к кассе: %v", typepodkluch)
		}
	}
	defer fptr.Close()

	// Формируем JSON для печати слипа
	lines := strings.Split(slip, "\n")
	items := make([]map[string]string, 0)

	for _, line := range lines {
		if strings.TrimSpace(line) != "" {
			items = append(items, map[string]string{
				"type":      "text",
				"text":      line,
				"alignment": "left",
			})
		}
	}

	slipData := map[string]interface{}{
		"type":  "nonFiscal",
		"items": items,
	}

	slipJSON, err := json.Marshal(slipData)
	if err != nil {
		return fmt.Errorf("ошибка формирования JSON для печати слипа: %v", err)
	}

	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("печать слипа на ККТ")
	result, err := p.handler.sendComandeAndGetAnswerFromKKT(fptr, string(slipJSON))
	if err != nil {
		return fmt.Errorf("ошибка печати слипа на ККТ: %v", err)
	}

	if !p.handler.successCommand(result) {
		return fmt.Errorf("ошибка печати слипа на ККТ: %v", result)
	}

	return nil
}

func (p *PrinterImpl) PrintText(fptr consttypes.IFptr10Interface, text string) error {
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("подключение к кассе")
	if ok, typepodkluch := p.handler.connectWithKassa(fptr, *p.handler.comport, *p.handler.ipaddresskkt, *p.handler.portkktatol, *p.handler.ipaddressservrkkt); !ok {
		if !*p.handler.emulation {
			return fmt.Errorf("ошибка подключения к кассе: %v", typepodkluch)
		}
	}
	defer fptr.Close()

	// Печать текста на ККТ
	fptr.SetParam(fptr10.LIBFPTR_PARAM_TEXT, text)
	err := fptr.PrintText()

	if err != nil {
		return fmt.Errorf("ошибка печати текста на ККТ: %v", err)
	}

	return nil
}
