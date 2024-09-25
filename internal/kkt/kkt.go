package kkt

import (
	"context"
	"fmt"
	"strconv"
	"strings"

	"service_print_check/internal/config"
	fptr10 "service_print_check/internal/fptr"
)

func CloseShift(ctx context.Context, cashier string) error {
	fptr, err := fptr10.NewSafe()
	if err != nil {
		return fmt.Errorf("ошибка инициализации драйвера ККТ: %w", err)
	}
	defer fptr.Destroy()

	if ok, connType := connectWithKassa(fptr); !ok {
		return fmt.Errorf("ошибка подключения к кассе (%s)", connType)
	}
	defer fptr.Close()

	closeShiftJSON := fmt.Sprintf(`{"type": "closeShift", "operator": {"name": "%s"}}`, cashier)
	result, err := sendCommandAndGetAnswerFromKKT(fptr, closeShiftJSON)
	if err != nil {
		return fmt.Errorf("ошибка отправки команды закрытия смены: %w", err)
	}

	if !successCommand(result) {
		return fmt.Errorf("ошибка закрытия смены: %s", result)
	}
	return nil
}

func sendCommandAndGetAnswerFromKKT(fptr *fptr10.IFptr, comJson string) (string, error) {
	fptr.SetParam(fptr10.LIBFPTR_PARAM_JSON_DATA, comJson)
	result := fptr.GetParamString(fptr10.LIBFPTR_PARAM_JSON_DATA)
	if strings.Contains(result, "Нет связи") {
		if ok, typepodkluch := connectWithKassa(fptr); !ok {
			descrErr := fmt.Sprintf("ошибка соединения с кассовым аппаратом %v", typepodkluch)
			fmt.Println(descrErr)
		}
	}
	return result, nil
}

func successCommand(resulJson string) bool {
	return !strings.Contains(strings.ToLower(resulJson), "ошибка") &&
		!strings.Contains(strings.ToLower(resulJson), "error")
}

func connectWithKassa(fptr *fptr10.IFptr) (bool, string) {
	conf := config.Current
	typeConnect := ""
	fptr.SetSingleSetting(fptr10.LIBFPTR_SETTING_MODEL, strconv.Itoa(fptr10.LIBFPTR_MODEL_ATOL_AUTO))
	if conf.IpServKKT != "" {
		fptr.SetSingleSetting(fptr10.LIBFPTR_SETTING_REMOTE_SERVER_ADDR, conf.IpServKKT)
		typeConnect = fmt.Sprintf("через сервер ККТ по IP %v", conf.IpServKKT)
	}
	if conf.Com == 0 {
		if conf.IpKKT != "" {
			fptr.SetSingleSetting(fptr10.LIBFPTR_SETTING_PORT, strconv.Itoa(fptr10.LIBFPTR_PORT_TCPIP))
			fptr.SetSingleSetting(fptr10.LIBFPTR_SETTING_IPADDRESS, conf.IpKKT)
			typeConnect = fmt.Sprintf("%v по IP %v ККТ на порт %v", typeConnect, conf.IpKKT, conf.PortKKT)
			if conf.PortKKT != 0 {
				fptr.SetSingleSetting(fptr10.LIBFPTR_SETTING_IPPORT, strconv.Itoa(conf.PortKKT))
			}
		} else {
			fptr.SetSingleSetting(fptr10.LIBFPTR_SETTING_PORT, strconv.Itoa(fptr10.LIBFPTR_PORT_USB))
			typeConnect = fmt.Sprintf("%v по USB", typeConnect)
		}
	} else {
		sComPorta := "COM" + strconv.Itoa(conf.Com)
		typeConnect = fmt.Sprintf("%v по COM порту %v", typeConnect, sComPorta)
		fptr.SetSingleSetting(fptr10.LIBFPTR_SETTING_PORT, strconv.Itoa(fptr10.LIBFPTR_PORT_COM))
		fptr.SetSingleSetting(fptr10.LIBFPTR_SETTING_COM_FILE, sComPorta)
		fptr.SetSingleSetting(fptr10.LIBFPTR_SETTING_BAUDRATE, strconv.Itoa(fptr10.LIBFPTR_PORT_BR_115200))
	}
	fptr.ApplySingleSettings()
	fptr.Open()
	return fptr.IsOpened(), typeConnect
}
