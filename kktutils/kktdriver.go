package kktutils

import (
	"encoding/json"
	"errors"
	"fmt"
	"service_print_check/consttypes"
	fptr10 "service_print_check/fptr"
	logsmy "service_print_check/packetlog"
	"strconv"
	"strings"
)

// SendCommandAndGetAnswerFromKKT отправляет команду на ККТ и получает ответ
func SendCommandAndGetAnswerFromKKT(fptr consttypes.IFptr10Interface, comJson string, emulation bool) (string, error) {
	var err error

	if fptr == nil {
		return "", fmt.Errorf("не инициализирован драйвер ККТ")
	}

	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("начало процедуры sendComandeAndGetAnswerFromKKT")
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("отправка команды на кассу: %s", comJson)
	fptr.SetParam(fptr10.LIBFPTR_PARAM_JSON_DATA, comJson)
	//fptr.ValidateJson()
	if !emulation {
		err = fptr.ProcessJson()
	}
	if err != nil {
		if !emulation {
			errorDescr := fmt.Sprintf("Ошибка ФР при отправке команды JSON: %v, описание: %s", err, comJson)
			logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
			logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("конец процедуры sendComandeAndGetAnswerFromKKT c ошибкой: %v", err)
			return "", fmt.Errorf("%s", errorDescr)
		}
	}
	resJson := fptr.GetParamString(fptr10.LIBFPTR_PARAM_JSON_DATA)
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("конец процедуры sendComandeAndGetAnswerFromKKT без ошибки")
	return resJson, nil
}

// SuccessCommand проверяет, успешно ли выполнена команда
func SuccessCommand(resulJson string) bool {
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("начали проверку успешности выполнения команды")
	res := true
	indOsh := strings.Contains(resulJson, "ошибка")
	indErr := strings.Contains(resulJson, "error")
	if indErr || indOsh {
		res = false
	}
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("завершили проверку успешности ыполнения команды")
	return res
}

// ConnectWithKassa подключается к кассе
func ConnectWithKassa(fptr consttypes.IFptr10Interface, comportint int, ipaddresskktper string, portkktper int, ipaddresssrvkktper string) (bool, string) {
	typeConnect := ""
	fptr.SetSingleSetting(fptr10.LIBFPTR_SETTING_MODEL, strconv.Itoa(fptr10.LIBFPTR_MODEL_ATOL_AUTO))
	if ipaddresssrvkktper != "" {
		fptr.SetSingleSetting(fptr10.LIBFPTR_SETTING_REMOTE_SERVER_ADDR, ipaddresssrvkktper)
		typeConnect = fmt.Sprintf("через сервер ККТ по IP %v", ipaddresssrvkktper)
	}
	if comportint == 0 {
		if ipaddresskktper != "" {
			fptr.SetSingleSetting(fptr10.LIBFPTR_SETTING_PORT, strconv.Itoa(fptr10.LIBFPTR_PORT_TCPIP))
			fptr.SetSingleSetting(fptr10.LIBFPTR_SETTING_IPADDRESS, ipaddresskktper)
			typeConnect = fmt.Sprintf("%v по IP %v ККТ на порт %v", typeConnect, ipaddresskktper, portkktper)
			if portkktper != 0 {
				fptr.SetSingleSetting(fptr10.LIBFPTR_SETTING_IPPORT, strconv.Itoa(portkktper))
			}
		} else {
			fptr.SetSingleSetting(fptr10.LIBFPTR_SETTING_PORT, strconv.Itoa(fptr10.LIBFPTR_PORT_USB))
			typeConnect = fmt.Sprintf("%v по USB", typeConnect)
		}
	} else {
		sComPorta := "COM" + strconv.Itoa(comportint)
		typeConnect = fmt.Sprintf("%v по COM порту %v", typeConnect, sComPorta)
		fptr.SetSingleSetting(fptr10.LIBFPTR_SETTING_PORT, strconv.Itoa(fptr10.LIBFPTR_PORT_COM))
		fptr.SetSingleSetting(fptr10.LIBFPTR_SETTING_COM_FILE, sComPorta)
		fptr.SetSingleSetting(fptr10.LIBFPTR_SETTING_BAUDRATE, strconv.Itoa(fptr10.LIBFPTR_PORT_BR_115200))
	}
	fptr.ApplySingleSettings()
	fptr.Open()
	return fptr.IsOpened(), typeConnect
}

func CloseKassa(fptr consttypes.IFptr10Interface) {
	if fptr == nil {
		logsmy.Logsmap[consttypes.LOGERROR].Println("fptr is nil")
		return
	}

	fptr.Close()
}

// CheckOpenShift проверяет, открыта ли смена и при необходимости открывает её
func CheckOpenShift(fptr consttypes.IFptr10Interface, openShiftIfClose bool, kassir string, emulation bool) (bool, error) {
	if fptr == nil {
		logsmy.Logsmap[consttypes.LOGERROR].Println("fptr is nil")
		return false, fmt.Errorf("fptr is nil")
	}

	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("получаем статус ККТ")
	getStatusKKTJson := "{\"type\": \"getDeviceStatus\"}"
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("отправляем команду getDeviceStatus")
	resgetStatusKKT, err := SendCommandAndGetAnswerFromKKT(fptr, getStatusKKTJson, emulation)
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("получили результат отправки команды getDeviceStatus: %s", resgetStatusKKT)
	if err != nil {
		errorDescr := fmt.Sprintf("ошибка (%v) получения статуса кассы", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
		return false, err
	}
	if !SuccessCommand(resgetStatusKKT) {
		errorDescr := fmt.Sprintf("ошибка (%v) получения статуса кассы", resgetStatusKKT)
		logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
		logsmy.LogginInFile(errorDescr)
		return false, errors.New(errorDescr)
	}

	var answerOfGetStatusofShift consttypes.TAnswerGetStatusOfShift
	err = json.Unmarshal([]byte(resgetStatusKKT), &answerOfGetStatusofShift)
	if err != nil {
		errorDescr := fmt.Sprintf("ошибка (%v) распарсивания статуса кассы", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
		return false, err
	}

	if answerOfGetStatusofShift.ShiftStatus.State == "expired" {
		errorDescr := "ошибка - смена на кассе уже истекла. Закройте смену"
		logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
		return false, errors.New(errorDescr)
	}

	if answerOfGetStatusofShift.ShiftStatus.State == "closed" {
		logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("смена на кассе закрыта. Открываем смену")
		if openShiftIfClose {
			if kassir == "" {
				errorDescr := "не указано имя кассира для открытия смены"
				logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
				return false, errors.New(errorDescr)
			}
			jsonOpenShift := fmt.Sprintf("{\"type\": \"openShift\",\"operator\": {\"name\": \"%v\"}}", kassir)
			resOpenShift, err := SendCommandAndGetAnswerFromKKT(fptr, jsonOpenShift, emulation)
			if err != nil {
				errorDescr := fmt.Sprintf("ошбика (%v) - не удалось открыть смену", err)
				logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
				return false, errors.New(errorDescr)
			}
			if !SuccessCommand(resOpenShift) {
				errorDescr := fmt.Sprintf("ошбика (%v) - не удалось открыть смену", resOpenShift)
				logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
				return false, errors.New(errorDescr)
			}
		} else {
			return false, nil
		}
	}

	return true, nil
}

// CashInOut выполняет операцию внесения или выплаты наличных
func CashInOut(fptr consttypes.IFptr10Interface, cashier string, cashSum float64, cashIn bool, emulation bool) error {
	strOperation := "внесение"
	if !cashIn {
		strOperation = "выплата"
	}
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Начали", strOperation, "наличных. Кассир:", cashier, "Сумма:", cashSum)

	strCashIn := "cashIn"
	if !cashIn {
		strCashIn = "cashOut"
	}
	cashInJSON := fmt.Sprintf(`{"type": "%s", "operator": {"name": "%s"}, "cashSum": %.2f}`, strCashIn, cashier, cashSum)
	result, err := SendCommandAndGetAnswerFromKKT(fptr, cashInJSON, emulation)
	if err != nil {
		logsmy.LogginInFile(fmt.Sprintf("ошибка отправки команды %s наличных: %v", strOperation, err))
		return fmt.Errorf("ошибка отправки команды %s наличных: %v", strOperation, err)
	}

	if !SuccessCommand(result) {
		logsmy.LogginInFile(fmt.Sprintf("ошибка %s наличных: %v", strOperation, result))
		return fmt.Errorf("ошибка %s наличных: %v", strOperation, result)
	}

	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("%s наличных выполнено успешно: %.2f", strOperation, cashSum)
	return nil
}

// CloseShift закрывает текущую смену
func CloseShift(fptr consttypes.IFptr10Interface, cashier string, emulation bool) (int, error) {
	if fptr == nil {
		return 0, fmt.Errorf("драйвер ККТ не инициализирован")
	}

	// Сначала проверим, открыта ли смена
	openShift, err := CheckOpenShift(fptr, false, cashier, emulation)
	if err != nil {
		return 0, fmt.Errorf("ошибка проверки смены: %v", err)
	}
	if !openShift {
		return 0, fmt.Errorf("смена не открыта")
	}
	// Закрываем смену
	closeShiftJson := fmt.Sprintf(`{
		"type": "closeShift",
		"operator": {
			"name": "%s"
		}
	}`, cashier)

	resCloseShift, err := SendCommandAndGetAnswerFromKKT(fptr, closeShiftJson, emulation)
	if err != nil {
		return 0, fmt.Errorf("ошибка закрытия смены: %v", err)
	}

	if !SuccessCommand(resCloseShift) {
		return 0, fmt.Errorf("ошибка закрытия смены: %s", resCloseShift)
	}

	// Получаем номер фискального документа из ответа
	var closeShiftResult map[string]interface{}
	if err := json.Unmarshal([]byte(resCloseShift), &closeShiftResult); err != nil {
		return 0, fmt.Errorf("ошибка разбора ответа закрытия смены: %v", err)
	}

	documentNumber := 0
	if fiscalParams, ok := closeShiftResult["fiscalParams"].(map[string]interface{}); ok {
		if fiscalDoc, ok := fiscalParams["fiscalDocumentNumber"].(float64); ok {
			documentNumber = int(fiscalDoc)
		}
	}

	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("Смена успешно закрыта. ФД: %d", documentNumber)
	return documentNumber, nil
}

// PrintXReport печатает X-отчет на ККТ
func PrintXReport(fptr consttypes.IFptr10Interface, cashier string, emulation bool) error {
	// Используем текущий X-отчет JSON
	xReportJSON := FormatXReportJSON()

	result, err := SendCommandAndGetAnswerFromKKT(fptr, xReportJSON, emulation)
	if err != nil {
		return fmt.Errorf("ошибка отправки команды печати X-отчета: %v", err)
	}

	if !SuccessCommand(result) {
		return fmt.Errorf("ошибка печати X-отчета: %v", result)
	}

	return nil
}

// PrintSlip печатает банковский слип на ККТ
func PrintSlip(fptr consttypes.IFptr10Interface, slip string, alignment string, emulation bool) error {
	if slip == "" {
		return fmt.Errorf("слип для печати не может быть пустым")
	}

	// Используем функцию для форматирования слипа
	slipJSON, err := FormatSlipToJSON(slip, alignment)
	if err != nil {
		return err
	}

	result, err := SendCommandAndGetAnswerFromKKT(fptr, slipJSON, emulation)
	if err != nil {
		return fmt.Errorf("ошибка печати слипа на ККТ: %v", err)
	}

	if !SuccessCommand(result) {
		return fmt.Errorf("ошибка печати слипа на ККТ: %v", result)
	}

	return nil
}

// PrintSlipDefault печатает слип с выравниванием по умолчанию (левое выравнивание)
func PrintSlipDefault(fptr consttypes.IFptr10Interface, slip string, emulation bool) error {
	return PrintSlip(fptr, slip, "left", emulation)
}
