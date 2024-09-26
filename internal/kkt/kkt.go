package kkt

import (
	"context"
	"fmt"
	"strconv"
	"strings"

	"service_print_check/internal/config"
	//fptr10 "service_print_check/internal/fptr"
	"service_print_check/internal/driver"
)

func CloseShift(ctx context.Context, cashier string) error {
	fptr, err := driver.NewFptrDriver()
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

func sendCommandAndGetAnswerFromKKT(fptr driver.IFptr10Interface, comJson string) (string, error) {
	fptr.SetParam(driver.LIBFPTR_PARAM_JSON_DATA, comJson)
	result := fptr.GetParamString(driver.LIBFPTR_PARAM_JSON_DATA)
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

func connectWithKassa(fptr driver.IFptr10Interface) (bool, string) {
	conf := config.Current
	typeConnect := ""
	fptr.SetSingleSetting(driver.LIBFPTR_SETTING_MODEL, strconv.Itoa(driver.LIBFPTR_MODEL_ATOL_AUTO))
	if conf.IpServKKT != "" {
		fptr.SetSingleSetting(driver.LIBFPTR_SETTING_REMOTE_SERVER_ADDR, conf.IpServKKT)
		typeConnect = fmt.Sprintf("через сервер ККТ по IP %v", conf.IpServKKT)
	}
	if conf.Com == 0 {
		if conf.IpKKT != "" {
			fptr.SetSingleSetting(driver.LIBFPTR_SETTING_PORT, strconv.Itoa(driver.LIBFPTR_PORT_TCPIP))
			fptr.SetSingleSetting(driver.LIBFPTR_SETTING_IPADDRESS, conf.IpKKT)
			typeConnect = fmt.Sprintf("%v по IP %v ККТ на порт %v", typeConnect, conf.IpKKT, conf.PortKKT)
			if conf.PortKKT != 0 {
				fptr.SetSingleSetting(driver.LIBFPTR_SETTING_IPPORT, strconv.Itoa(conf.PortKKT))
			}
		} else {
			fptr.SetSingleSetting(driver.LIBFPTR_SETTING_PORT, strconv.Itoa(driver.LIBFPTR_PORT_USB))
			typeConnect = fmt.Sprintf("%v по USB", typeConnect)
		}
	} else {
		sComPorta := "COM" + strconv.Itoa(conf.Com)
		typeConnect = fmt.Sprintf("%v по COM порту %v", typeConnect, sComPorta)
		fptr.SetSingleSetting(driver.LIBFPTR_SETTING_PORT, strconv.Itoa(driver.LIBFPTR_PORT_COM))
		fptr.SetSingleSetting(driver.LIBFPTR_SETTING_COM_FILE, sComPorta)
		fptr.SetSingleSetting(driver.LIBFPTR_SETTING_BAUDRATE, strconv.Itoa(driver.LIBFPTR_PORT_BR_115200))
	}
	fptr.ApplySingleSettings()
	fptr.Open()
	return fptr.IsOpened(), typeConnect
}

func sendComandeAndGetAnswerFromKKT(fptr driver.IFptr10Interface, comJson string) (string, error) {
	var err error
	fptr.SetParam(driver.LIBFPTR_PARAM_JSON_DATA, comJson)
	//fptr.ValidateJson()
	err = fptr.ProcessJson()
	if err != nil {
		desrError := fmt.Sprintf("ошибка (%v) выполнение команды %v на кассе", err, comJson)
		return desrError, err
	}
	result := fptr.GetParamString(driver.LIBFPTR_PARAM_JSON_DATA)
	if strings.Contains(result, "Нет связи") {
		if ok, typepodkluch := connectWithKassa(fptr, *comport, *ipaddresskkt, *portkktatol, *ipaddressservrkkt); !ok {
			descrErr := fmt.Sprintf("ошибка соединения с кассовым аппаратом %v", typepodkluch)
			fmt.Println(descrErr)
			logsmy.Logsmap[consttypes.LOGERROR].Println(descrErr)
			if !*emulation {
				logsmy.LogginInFile(descrErr)
				println("Нажмите любую клавишу...")
				//input.Scan()
				//logsmy.Logsmap[consttypes.LOGERROR].Panic(descrErr)
			}
		} else {
			logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("подключение к кассе а порт %v прошло успешно", *comport)
		}
	}
	return result, nil
} //sendComandeAndGetAnswerFromKKT
