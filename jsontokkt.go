//go:generate ./resource/goversioninfo.exe -icon=resource/icon.ico -manifest=resource/goversioninfo.exe.manifest
package main

import (
	"bufio"
	consttypes "clientrabbit/consttypes"
	fptr10 "clientrabbit/fptr"
	logsmy "clientrabbit/packetlog"
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"log"
	"os"
	"strconv"
	"strings"
)

var clearLogsProgramm = flag.Bool("clearlogs", true, "очистить логи программы")
var LogsDebugs = flag.Int("debug", 0, "уровень логирования всех действий, чем выше тем больше логов")
var comport = flag.Int("com", 0, "ком порт кассы")
var CassirName = flag.String("cassir", "", "имя кассира")
var ipaddresskkt = flag.String("ipkkt", "", "ip адрес ккт")
var portkktatol = flag.Int("portipkkt", 0, "порт ip ккт")
var ipaddressservrkkt = flag.String("ipservkkt", "", "ip адрес сервера ккт")
var emulation = flag.Bool("emul", false, "эмуляция")
var dontprintrealfortest = flag.Bool("test", false, "тест - не печатать реальный чек")
var emulatmistakes = flag.Bool("emulmist", false, "эмуляция ошибок")
var emulatmistakesOpenCheck = flag.Bool("emulmistopencheck", false, "эмуляция ошибок открытия чека")

const Version_of_program = "2024_07_30_02"

func main() {
	var err error
	var fptr *fptr10.IFptr
	//выводим информацию о программе
	//читаем параметры запуска программы
	//открываем лог файлы
	//читаем настроку com - порта в директории json - заданий
	//подключаемся к кассовому аппарату
	//печатаем чек
	//если были ошибку при печати чека, то переходим к следующему заданию
	//эмулируем ошибку, если режим эмуляции ошибки включен
	//читаем информацию об результате выполнения команды
	//если команда выполнена успешно, то записываем в таблицу напечатанных чеков
	//если команда выполнена неуспешно, то проверяем не превышен ли количество чеков в смену,
	//и если превышено, то закрываем и открываем смену
	//закрывес соединение с кассой меркурий если было установлено
	//выводим информацию об количестве напечтатнных чеков
	//
	/////////////////**************************///////////////////////
	//
	//выводим информацию о программе
	runDescription := "программа версии " + Version_of_program + " распечатка чеков из json заданий запущена"
	fmt.Println(runDescription)
	defer fmt.Println("программа версии " + Version_of_program + " распечатка чеков из json заданий остановлена")
	//читаем параметры запуска программы
	fmt.Println("парсинг параметров запуска программы")
	flag.Parse()
	fmt.Println("Эмулирование ККТ", *emulation)
	fmt.Println("Уровень логирования: ", *LogsDebugs)
	clearLogsDescr := fmt.Sprintf("Очистить логи программы %v", *clearLogsProgramm)
	fmt.Println(clearLogsDescr)
	//открываем лог файлы
	fmt.Println("инициализация лог файлов программы")
	input := bufio.NewScanner(os.Stdin)
	descrMistake, err := logsmy.InitializationsLogs(*clearLogsProgramm, *LogsDebugs)
	defer logsmy.CloseDescrptorsLogs()
	if err != nil {
		fmt.Fprint(os.Stderr, descrMistake)
		println("Нажмите любую клавишу...")
		input.Scan()
		log.Panic(descrMistake)
	}
	logsmy.LogginInFile(runDescription)
	logsmy.LogginInFile(clearLogsDescr)
	//читаем настроку com - порта в директории json - заданий
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("порт кассы", *comport)
	//подключаемся к кассовому аппарату
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("Тип кассы atol")
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("инициализация драйвера атол")
	fptr, err = fptr10.NewSafe()
	if err != nil {
		descrError := fmt.Sprintf("Ошибка (%v) инициализации драйвера ККТ атол", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(descrError)
		println("Нажмите любую клавишу...")
		input.Scan()
		log.Panic(descrError)
	}
	defer fptr.Destroy()
	fmt.Println(fptr.Version())
	//сединение с кассой
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("соединение с кассой")
	if ok, typepodkluch := connectWithKassa(fptr, *comport, *ipaddresskkt, *portkktatol, *ipaddressservrkkt); !ok {
		descrErr := fmt.Sprintf("ошибка соединения с кассовым аппаратом %v", typepodkluch)
		logsmy.Logsmap[consttypes.LOGERROR].Println(descrErr)
		if !*emulation {
			println("Нажмите любую клавишу...")
			input.Scan()
			log.Panic(descrErr)
		}
	} else {
		logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("подключение к кассе на порт %v прошло успешно", *comport)
	}
	defer fptr.Close()
	//печатаем чек
	jsonOfCheck := ""
	logstr := fmt.Sprintf("посылаем команду печати чека кассу json файл %v", jsonOfCheck)
	logsmy.LogginInFile(logstr)
	resulOfCommand := ""
	resulOfCommand, err = sendComandeAndGetAnswerFromKKT(fptr, jsonOfCheck)
	//если были ошибку при печати чека, то переходим к следующему заданию
	if err != nil {
		errorDescr := fmt.Sprintf("ошибка (%v) печати чека", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
	}
	logsmy.LogginInFile("послали команду печати чека кассу json файл")
	//эмулируем ошибку, если режим эмуляции ошибки включен
	if *emulatmistakes {
		logsmy.LogginInFile("производим ошибку печати чека")
		logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("производим ошибку печати чека")
		resulOfCommand = "{\"result\": \"error - эмуляция ошибки\"}"
	}
	//читаем информацию об результате выполнения команды
	//если команда выполнена успешно, то записываем в таблицу напечатанных чеков
	//если команда выполнена неуспешно, то проверяем не превышен ли количество чеков в смену,
	//и если превышено, то закрываем и открываем смену
	if successCommand(resulOfCommand) {
		//при успешной печати чека, записываем данные о номере напечатнного чека
		fmt.Println("Чек успешно напечатан")
	} else {
		descrError := fmt.Sprintf("ошибка (%v) печати чека %v атол", resulOfCommand, jsonOfCheck)
		logsmy.Logsmap[consttypes.LOGERROR].Printf(descrError)
		logsmy.LogginInFile(descrError)
	}
} //main

func sendComandeAndGetAnswerFromKKT(fptr *fptr10.IFptr, comJson string) (string, error) {
	var err error
	logsmy.LogginInFile("начало процедуры sendComandeAndGetAnswerFromKKT")
	//return "", nil
	fptr.SetParam(fptr10.LIBFPTR_PARAM_JSON_DATA, comJson)
	//fptr.ValidateJson()
	if !*emulation {
		err = fptr.ProcessJson()
	}
	if err != nil {
		if !*emulation {
			desrError := fmt.Sprintf("ошибка (%v) выполнение команды %v на кассе", err, comJson)
			logsmy.Logsmap[consttypes.LOGERROR].Println(desrError)
			logstr := fmt.Sprint("конец процедуры sendComandeAndGetAnswerFromKKT c ошибкой", err)
			logsmy.LogginInFile(logstr)
			return desrError, err
		}
	}
	result := fptr.GetParamString(fptr10.LIBFPTR_PARAM_JSON_DATA)
	if strings.Contains(result, "Нет связи") {
		logsmy.LogginInFile("нет связи: переподключаемся")
		if ok, typepodkluch := connectWithKassa(fptr, *comport, *ipaddresskkt, *portkktatol, *ipaddressservrkkt); !ok {
			descrErr := fmt.Sprintf("ошибка соединения с кассовым аппаратом %v", typepodkluch)
			logsmy.Logsmap[consttypes.LOGERROR].Println(descrErr)
			if !*emulation {
				println("Нажмите любую клавишу...")
				//input.Scan()
				log.Panic(descrErr)
			}
		} else {
			logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("подключение к кассе на порт %v прошло успешно", *comport)
		}
	}
	logsmy.LogginInFile("конец процедуры sendComandeAndGetAnswerFromKKT без ошибки")
	return result, nil
} //sendComandeAndGetAnswerFromKKT

func successCommand(resulJson string) bool {
	res := true
	indOsh := strings.Contains(resulJson, "ошибка")
	indErr := strings.Contains(resulJson, "error")
	if indErr || indOsh {
		res = false
	}
	return res
} //successCommand

func connectWithKassa(fptr *fptr10.IFptr, comportint int, ipaddresskktper string, portkktper int, ipaddresssrvkktper string) (bool, string) {
	//if !strings.Contains(comport, "COM") {
	//	sComPorta = "COM" + comport
	//}
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
func connectToKKT(fptr *fptr10.IFptr, createComObj bool) (string, error) {
	var err error
	logsmy.LogginInFile("снова создаём объект драйвера...")
	if createComObj {
		fptr, err = fptr10.NewSafe()
	}
	if err != nil {
		descrError := fmt.Sprintf("ошибка (%v) инициализации драйвера ККТ атол", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(descrError)
		return descrError, errors.New(descrError)
	}
	//сединение с кассой
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("соединение с кассой...")
	if ok, typepodkluch := connectWithKassa(fptr, *comport, *ipaddresskkt, *portkktatol, *ipaddressservrkkt); !ok {
		descrErr := fmt.Sprintf("ошибка сокдинения с кассовым аппаратом %v", typepodkluch)
		logsmy.Logsmap[consttypes.LOGERROR].Println(descrErr)
		if !*emulation {
			return descrErr, errors.New(descrErr)
		}
	} else {
		logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("подключение к кассе на порт %v прошло успешно", *comport)
	}
	return "", nil
}
func disconnectWithKKT(fptr *fptr10.IFptr, destroyComObject bool) {
	fptr.Close()
	if destroyComObject {
		fptr.Destroy()
	}
}
func reconnectToKKT(fptr *fptr10.IFptr) error {
	fptr.Close()
	fptr.Destroy()
	fptr, err := fptr10.NewSafe()
	if err != nil {
		descrError := fmt.Sprintf("ошибка (%v) инициализации драйвера ККТ атол", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(descrError)
		return errors.New(descrError)
		//println("Нажмите любую клавишу...")
		//input.Scan()
		//log.Panic(descrError)
	}
	//defer fptr.Destroy()
	fmt.Println(fptr.Version())
	//сединение с кассой
	logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Println("соединение с кассой")
	//if err != nil {
	//	desrErr := fmt.Sprintf("ошибка (%v) чтения параметра com порт соединения с кассой", err)
	//	logsmy.Logsmap[consttypes.LOGERROR].Println(desrErr)
	//	return errors.New(desrErr)
	//	//println("Нажмите любую клавишу...")
	//	//input.Scan()
	//	//log.Panic(desrErr)
	//}
	if ok, typepodkluch := connectWithKassa(fptr, *comport, *ipaddresskkt, *portkktatol, *ipaddressservrkkt); !ok {
		//if !connectWithKassa(fptr, *comport, *ipaddresskkt, *ipaddressservrkkt) {
		descrErr := fmt.Sprintf("ошибка сокдинения с кассовым аппаратом %v", typepodkluch)
		logsmy.Logsmap[consttypes.LOGERROR].Println(descrErr)
		if !*emulation {
			return errors.New(descrErr)
			//println("Нажмите любую клавишу...")
			//input.Scan()
			//log.Panic(descrErr)
		}
	} else {
		logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("подключение к кассе на порт %v прошло успешно", *comport)
	}
	return nil
	//defer fptr.Close()
}

func checkOpenShift(fptr *fptr10.IFptr, openShiftIfClose bool, kassir string) (bool, error) {
	logsmy.LogginInFile("получаем статус ККТ")
	getStatusKKTJson := "{\"type\": \"getDeviceStatus\"}"
	resgetStatusKKT, err := sendComandeAndGetAnswerFromKKT(fptr, getStatusKKTJson)
	if err != nil {
		errorDescr := fmt.Sprintf("ошибка (%v) получения статуса кассы", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
		return false, err
	}
	if !successCommand(resgetStatusKKT) {
		errorDescr := fmt.Sprintf("ошибка (%v) получения статуса кассы", resgetStatusKKT)
		logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
		//logsmy.LogginInFile(errorDescr)
		return false, errors.New(errorDescr)
	}
	logsmy.LogginInFile("получили статус кассы")
	//проверяем - открыта ли смена
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
		if openShiftIfClose {
			if kassir == "" {
				errorDescr := "не указано имя кассира для открытия смены"
				logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
				return false, errors.New(errorDescr)
			}
			jsonOpenShift := fmt.Sprintf("{\"type\": \"openShift\",\"operator\": {\"name\": \"%v\"}}", kassir)
			resOpenShift, err := sendComandeAndGetAnswerFromKKT(fptr, jsonOpenShift)
			if err != nil {
				errorDescr := fmt.Sprintf("ошбика (%v) - не удалось открыть смену", err)
				logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
				return false, errors.New(errorDescr)
			}
			if !successCommand(resOpenShift) {
				errorDescr := fmt.Sprintf("ошбика (%v) - не удалось открыть смену", resOpenShift)
				logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
				return false, errors.New(errorDescr)
			}
		} else {
			return false, nil
		}
	}
	return true, nil
} //checkOpenShift
