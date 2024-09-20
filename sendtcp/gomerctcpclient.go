package mercuriy

import (
	"bytes"
	consttypes "checkservice/consttypes"
	logsmy "checkservice/packetlog"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net"
	"strconv"
	"time"
)

type TMercPayments struct {
	Cash          int `json:"cash"`
	Ecash         int `json:"ecash"`
	Prepayment    int `json:"prepayment"`
	Credit        int `json:"credit"`
	Consideration int `json:"consideration"`
}

type TMercCloseCheck struct {
	SessionKey  string        `json:"sessionKey"`
	Command     string        `json:"command"`
	SendCheckTo string        `json:"sendCheckTo,omitempty"`
	Payment     TMercPayments `json:"payment"`
}

type TAgentMerc struct {
	Code          int      `json:"code"`
	PayingOp      string   `json:"payingOp,omitempty"`
	PayingPhone   []string `json:"payingPhone,omitempty"`
	TransfName    string   `json:"transfName,omitempty"`
	TransfINN     string   `json:"transfINN,omitempty"`
	TransfAddress string   `json:"transfAddress,omitempty"`
	TransfPhone   string   `json:"transfPhone,omitempty"`
	OperatorPhone []string `json:"operatorPhone,omitempty"`
	SupplierPhone []string `json:"supplierPhone,omitempty"`
	SupplierINN   string   `json:"supplierINN,omitempty"`
	SupplierName  string   `json:"supplierName,omitempty"`
}

// структура информации о кассе меркурий
type TMercKKTInfo struct {
	RegNum string `json:"regNum"`
}

// структура регистрации кассы меркурий
type TMercRegistrationInfo struct {
	Kkt       TMercKKTInfo `json:"kkt"`
	TaxSystem []int        `json:"taxSystem"`
}

type TShiftInfoMerc struct {
	IsOpen      bool `json:"isOpen"`
	Is24Expired bool `json:"is24Expired"`
	Num         int  `json:"num"`
}

type TCheckInfoMerc struct {
	IsOpen bool `json:"isOpen"`
	Num    int  `json:"num"`
}

type TFnInfoMerc struct {
	Status int    `json:"status"`
	FnNum  string `json:"fnNum"`
}

type TCorrectedDataMerc struct {
	McType         int    `json:"mcType"`
	McGoodsID      string `json:"mcGoodsID"`
	ProcessingMode int    `json:"processingMode"`
}

type TBuyerInfoMerc struct {
	BuyerName string `json:"buyerName,omitempty"`
	BuyerINN  string `json:"buyerID,omitempty"`
}

type TCashierInfoMerc struct {
	CashierName string `json:"cashierName"`
	CashierINN  string `json:"cashierID,omitempty"`
}

type TMercOpenCheck struct {
	SessionKey  string           `json:"sessionKey"`
	Command     string           `json:"command"`
	CheckType   int              `json:"checkType"`
	TaxSystem   int              `json:"taxSystem"`
	PrintDoc    bool             `json:"printDoc,omitempty"`
	CashierInfo TCashierInfoMerc `json:"cashierInfo"`
	BuyerInfo   *TBuyerInfoMerc  `json:"buyerInfo,omitempty"`
}

type TOnlineCheckMerc struct {
	Result                   int                 `json:"result"`
	Description              string              `json:"description"`
	ProcessingResult         int                 `json:"processingResult"`
	McCheckResult            bool                `json:"mcCheckResult"`
	PlannedStatusCheckResult int                 `json:"plannedStatusCheckResult"`
	McCheckResultRaw         int                 `json:"mcCheckResultRaw"`
	CorrectedData            *TCorrectedDataMerc `json:"correctedData,omitempty"`
}

// структура ответа на запрос к ккт меркурий
type TAnswerMercur struct {
	Result           int                    `json:"result"`
	Description      string                 `json:"description"`
	SessionKey       string                 `json:"sessionKey,omitempty"`
	ProtocolVer      string                 `json:"protocolVer,omitempty"`
	FnNum            string                 `json:"fnNum,omitempty"`
	KktNum           string                 `json:"kktNum,omitempty"`
	Model            string                 `json:"model,omitempty"`
	ShiftInfo        *TShiftInfoMerc        `json:"shiftInfo,omitempty"`
	CheckInfo        *TCheckInfoMerc        `json:"checkInfo,omitempty"`
	FnInfo           *TFnInfoMerc           `json:"fnInfo,omitempty"`
	IsCompleted      bool                   `json:"isCompleted,omitempty"`
	McCheckResultRaw int                    `json:"mcCheckResultRaw,omitempty"`
	OnlineCheck      *TOnlineCheckMerc      `json:"onlineCheck,omitempty"`
	GoodsNum         int                    `json:"goodsNum,omitempty"`
	ShiftNum         int                    `json:"shiftNum,omitempty"`
	CheckNum         int                    `json:"checkNum,omitempty"`
	FiscalDocNum     int                    `json:"fiscalDocNum,omitempty"`
	FiscalSign       string                 `json:"fiscalSign,omitempty"`
	DriverVer        string                 `json:"driverVer,omitempty"`
	DriverBaseVer    string                 `json:"driverBaseVer,omitempty"`
	RegistrationInfo *TMercRegistrationInfo `json:"registrationInfo,omitempty"`
}

// параметры подключения
type TConnectionParams struct {
	IPAddress string
	Port      int
	ComPort   int
}

// параметры авторизации
type TAuthParams struct {
	UserInt   int
	PasswUser string
}

var testnomsessii int

func GetSNOByDefault(connectionParams TConnectionParams, sessionkey string, emulationParams consttypes.TEmulationParams) (int, error) {
	var resMerc TAnswerMercur
	jsonmerc := []byte(fmt.Sprintf("{\"sessionKey\":\"%v\", \"command\":\"GetRegistrationInfo\"}", sessionkey))
	buffAnsw, err := sendCommandTCPMerc(jsonmerc, connectionParams)
	if err != nil {
		return -1, err
	}
	err = json.Unmarshal(buffAnsw, &resMerc)
	if err != nil {
		logsmy.Logsmap[consttypes.LOGERROR].Printf("ошибка (%v) маршалинга результата запроса о резултататах регистрации кассы меркурий\n", err)
		return -1, err
	}
	if resMerc.Result != 0 {
		logsmy.Logsmap[consttypes.LOGERROR].Printf("ошибка (%v) запроса о резултататах регистрации кассы меркурий\n", resMerc.Description)
		err = fmt.Errorf(resMerc.Description)
		if !emulationParams.Emulation {
			return -1, err
		} else {
			resMerc.RegistrationInfo = new(TMercRegistrationInfo)
			resMerc.RegistrationInfo.TaxSystem = []int{5}
		}
	}
	if len(resMerc.RegistrationInfo.TaxSystem) != 1 {
		err := errors.New("касса зарегистрирована на больше чем одна система налогообложение")
		logsmy.Logsmap[consttypes.LOGERROR].Printf("касса зарегистрирована на больше чем одна система налогообложение")
		return -1, err
	}
	return resMerc.RegistrationInfo.TaxSystem[0], nil
}

func PrintCheck(
	connectionParams TConnectionParams,
	checkData consttypes.TCheckData,
	emulationParams *consttypes.TEmulationParams,
	authParams *TAuthParams,
) (string, error) {
	var resMerc, resMercCancel TAnswerMercur
	var answer []byte
	var answerclosecheck []byte
	var errclosecheck, errOfOpenCheck error

	// Проверка и использование необязательных параметров
	if emulationParams == nil {
		defaultParams := consttypes.NewDefaultEmulationParams()
		emulationParams = &defaultParams
	}
	if authParams == nil {
		defaultAuthParams := NewDefaultAuthParams()
		authParams = &defaultAuthParams
	}
	answer, err := opensession(connectionParams, *authParams)
	if err != nil {
		descrError := "ошибка открытия сессии к ккт меркурий"
		logsmy.Logsmap[consttypes.LOGERROR].Printf(descrError)
		err = errors.Join(err, errors.New(descrError))
		return descrError, err
	}
	err = json.Unmarshal(answer, &resMerc)
	if err != nil {
		descrError := "ошибка при разобре ответа при отрытии сессии покдлючения к ККТ меркурий"
		logsmy.Logsmap[consttypes.LOGERROR].Printf(descrError)
		err = errors.Join(err, errors.New(descrError))
		return descrError, err
	}
	if resMerc.Result != 0 || resMerc.SessionKey == "" {
		descrError := "ошибка при подключении к ккт меркурий"
		logsmy.Logsmap[consttypes.LOGERROR].Printf(descrError)
		err = fmt.Errorf(resMerc.Description)
		err = errors.Join(err, errors.New(descrError))
		if !emulationParams.Emulation {
			return descrError, err
		} else {
			testnomsessii = testnomsessii + 1
			resMerc.SessionKey = "эмуляция" + strconv.Itoa(testnomsessii)
			logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("эмуляция сессии %v", resMerc.SessionKey)
		}
	}
	sessionkey := resMerc.SessionKey
	defer Closesession(connectionParams, &sessionkey)

	checkContainsMarks := checkContainsMarks(checkData)
	if checkContainsMarks {
		needOpenShift := true
		checkOpenShift(connectionParams, needOpenShift, checkData.Cashier, sessionkey, *authParams, *emulationParams)
	}

	if checkContainsMarks {
		BreakAndClearProccessOfMarks(connectionParams, sessionkey, *authParams)
		err = checkAndRunsCheckingMarksByCheck(&checkData, connectionParams, sessionkey, *authParams, *emulationParams)
	}

	checheaderkmerc, err := convertToMercHeader(checkData)
	checheaderkmerc.SessionKey = sessionkey
	if err != nil {
		descrError := fmt.Sprintf("ошибка конвертации данных чека (%v) в шапку чека меркурия: %v", checkData, err)
		logsmy.Logsmap[consttypes.LOGERROR].Printf(descrError)
		return "", fmt.Errorf("%s: %w", descrError, err)
	}
	headercheckmerc, err := json.Marshal(checheaderkmerc)
	if err != nil {
		descrError := fmt.Sprintf("ошибка маршалинга шапки чека (%v): %v", checheaderkmerc, err)
		logsmy.Logsmap[consttypes.LOGERROR].Printf(descrError)
		return "", fmt.Errorf(descrError)
	}
	answer, err = opencheck(connectionParams, headercheckmerc)
	if err != nil {
		descrError := fmt.Sprintf("Ошибка открытия чека (%v) для кассы Меркурий: %v\n", string(answer), err)
		logsmy.LogginInFile(descrError)
		err = errors.Join(err, errors.New(descrError))
		return descrError, err
	}
	err = json.Unmarshal(answer, &resMerc)
	if err != nil {
		descrError := fmt.Sprintf("Ошибка разбора ответа (%v) при открытии чека для кассы Меркурий: %v\n", string(answer), err)
		logsmy.LogginInFile(descrError)
		return "", fmt.Errorf("%s: %w", descrError, err)
	}
	if resMerc.Result != 0 { //если не получилось открыть чек, отменяем его и пробуем отрыть заново
		descrError := fmt.Sprintf("ошибка (%v) открытия чека для кассы меркурий (попытка 1)", resMerc.Description)
		errOfOpenCheck = fmt.Errorf(descrError)
		logsmy.LogginInFile(fmt.Sprintf("ошибка (%v) 1-ой попытки открытия чека. Пробуемотменить чек и открыть заново \n", errOfOpenCheck))
		logsmy.LogginInFile("отменяем предыдущий чек \n")
		answerCancel, errCancel := cancelcheck(connectionParams, &sessionkey) //отменяем предыдущий чек
		if errCancel != nil {
			logsmy.LogginInFile(fmt.Sprintf("ошибка (%v) отмены предыдущего чека \n", errCancel))
			errOfOpenCheck = errors.Join(errOfOpenCheck, errCancel)
		} else {
			errUnMarshCancel := json.Unmarshal(answerCancel, &resMercCancel)
			if errUnMarshCancel != nil {
				logsmy.LogginInFile(fmt.Sprintf("ошибка (%v) рапзборап ответа отмены предыдущего чека \n", errUnMarshCancel))
				errOfOpenCheck = errors.Join(errOfOpenCheck, errUnMarshCancel)
			} else {
				logsmy.LogginInFile(fmt.Sprintf("результат (%v) отмены предыдущего чека \n", resMercCancel.Description))
				if resMercCancel.Result != 0 {
					descrError := fmt.Sprintf("ошибка (%v) отмены чека для кассы меркурий", resMercCancel.Description)
					errOfOpenCheck = errors.Join(errOfOpenCheck, fmt.Errorf(descrError))
				} else {
					answer, err = opencheck(connectionParams, headercheckmerc) //открываем заново чек
					if err != nil {
						err = json.Unmarshal(answer, &resMerc) //разбираем ответ
						if err != nil {
							logsmy.LogginInFile(fmt.Sprintf("ошибка (%v) разбора ответа отмены чека\n", err))
						}
					}
				}
			}
		}
	}
	if resMerc.Result != 0 { //если не получилось открыть чек
		descrError := fmt.Sprintf("ошибка (%v) открытия чека для кассы меркурий", resMerc.Description)
		logsmy.Logsmap[consttypes.LOGERROR].Printf(descrError)
		err = errors.Join(errOfOpenCheck, errors.New(descrError))
		if !emulationParams.Emulation {
			return descrError, err
		}
	}
	for _, pos := range checkData.TableData {
		//mercPos, err := convertAtolPosToMercPos(currPos)
		mercPos, err := convertToMercPos(pos)
		mercPos.SessionKey = sessionkey
		if err != nil {
			descrError := fmt.Sprintf("ошибка формирования структуры позиции для кассы меркурий из позиции json-задания (%v)", pos)
			err = errors.Join(err, errors.New(descrError))
			return descrError, err
		}
		mercPosJsonBytes, err := json.Marshal(mercPos)
		if err != nil {
			descrError := fmt.Sprintf("ошибка маршалинга структуры позиции для кассы меркурий из (%v)", mercPos)
			err = errors.Join(err, errors.New(descrError))
			return descrError, err
		}
		answer, err = addpos(connectionParams, mercPosJsonBytes)
		if err != nil {
			descrError := fmt.Sprintf("ошибка добавления позиции %v в чек для кассы меркурий", mercPosJsonBytes)
			err = errors.Join(err, errors.New(descrError))
			if !emulationParams.Emulation {
				return descrError, err
			}
		}
		err = json.Unmarshal(answer, &resMerc)
		if err != nil {
			descrError := fmt.Sprintf("ошибка маршалинга результата %v добавления позиции в чек для кассы меркурий", resMerc)
			err = errors.Join(err, errors.New(descrError))
			return descrError, err
		}
		if resMerc.Result != 0 {
			descrError := fmt.Sprintf("ошибка добавления позиции %v в чек для кассы меркурий", mercPosJsonBytes)
			err = fmt.Errorf(resMerc.Description)
			err = errors.Join(err, errors.New(descrError))
			if !emulationParams.Emulation {
				return descrError, err
			}
		}
	}
	//checkclosekmerc := convertAtolToMercCloseCheck(checkatol)
	checkclosekmerc := convertMercCloseCheck(checkData)
	checkclosekmerc.SessionKey = sessionkey
	checkclosekmercbytes, err := json.Marshal(checkclosekmerc)
	if err != nil {
		descrError := "ошибка формирования данных для закрытия чек кассы меркурий"
		err = errors.Join(err, errors.New(descrError))
		return descrError, err
	}
	if !emulationParams.DontPrintRealForTest {
		answerclosecheck, errclosecheck = closecheck(connectionParams, checkclosekmercbytes)
	} else {
		answerclosecheck, errclosecheck = cancelcheck(connectionParams, &sessionkey)
	}
	if errclosecheck != nil {
		descrError := "ошибка закрытия чека для кассы меркурий"
		err = errors.Join(err, errors.New(descrError))
		return descrError, errclosecheck
	}
	errclosecheck = json.Unmarshal(answerclosecheck, &resMerc)
	if errclosecheck != nil {
		descrError := "ошибка разбора резульата закрытия чека для кассы меркурий"
		err = errors.Join(err, errors.New(descrError))
		return descrError, errclosecheck
	}
	if resMerc.Result != 0 {
		descrError := "ошибка закрытия чека для кассы меркурий"
		errclosecheck = fmt.Errorf(resMerc.Description)
		err = errors.Join(err, errors.New(descrError))
		if !emulationParams.Emulation {
			return descrError, errclosecheck
		} else {
			errclosecheck = nil
		}
	}
	return string(answerclosecheck), errclosecheck
} //PrintCheck

func PrintXReport(connectionParams TConnectionParams, authParams *TAuthParams, emulationParams *consttypes.TEmulationParams) (TAnswerMercur, error) {
	var resMerc TAnswerMercur
	if authParams == nil {
		defaultAuthParams := NewDefaultAuthParams()
		authParams = &defaultAuthParams
	}
	if emulationParams == nil {
		defaultEmulationParams := consttypes.NewDefaultEmulationParams()
		emulationParams = &defaultEmulationParams
	}
	sessionkey, _, err := checkStatsuConnectionKKT(connectionParams, "", *authParams, *emulationParams)
	if err != nil {
		return resMerc, err
	}
	defer Closesession(connectionParams, &sessionkey)
	jsonmerc := []byte(fmt.Sprintf("{\"sessionKey\":\"%v\", \"command\":\"PrintReport\", \"reportCode\": 1}", sessionkey))
	answer, err := sendCommandTCPMerc(jsonmerc, connectionParams)
	if err != nil {
		return resMerc, err
	}
	err = json.Unmarshal(answer, &resMerc)
	if err != nil {
		return resMerc, err
	}
	return resMerc, nil
} //printXReport

func CloseShift(connectionParams TConnectionParams, cashier string, authParams *TAuthParams, emulationParams *consttypes.TEmulationParams) (TAnswerMercur, error) {
	var resMerc TAnswerMercur
	if authParams == nil {
		defaultAuthParams := NewDefaultAuthParams()
		authParams = &defaultAuthParams
	}
	if emulationParams == nil {
		defaultEmulationParams := consttypes.NewDefaultEmulationParams()
		emulationParams = &defaultEmulationParams
	}
	sessionkey, _, err := checkStatsuConnectionKKT(connectionParams, "", *authParams, *emulationParams)
	if err != nil {
		return resMerc, err
	}
	defer Closesession(connectionParams, &sessionkey)
	jsonmerc := []byte(fmt.Sprintf("{\"sessionKey\":\"%v\", \"command\":\"CloseShift\", \"cashierInfo\": {\"cashierName\": \"%v\"}}", sessionkey, cashier))
	answer, err := sendCommandTCPMerc(jsonmerc, connectionParams)
	if err != nil {
		return resMerc, err
	}
	err = json.Unmarshal(answer, &resMerc)
	if err != nil {
		return resMerc, err
	}
	return resMerc, nil
}

func checkStatsuConnectionKKT(connectionParams TConnectionParams, sessionkey string, authParams TAuthParams, emulationParams consttypes.TEmulationParams) (string, TAnswerMercur, error) {
	var resMerc TAnswerMercur
	answerbytesserver, errStatusServer := getStatusServerKKT(connectionParams)
	if errStatusServer != nil {
		descrError := "ошибка получения статуса сервера ккт меркурий"
		logsmy.LogginInFile(descrError)
		return "", resMerc, fmt.Errorf(descrError)
	}
	errUnmarshServer := json.Unmarshal(answerbytesserver, &resMerc)
	if errUnmarshServer != nil {
		descrError := fmt.Sprintf("ошибка распаковки ответа %v сервера ккт меркурий", string(answerbytesserver))
		logsmy.LogginInFile(descrError)
		return "", resMerc, fmt.Errorf(descrError)
	}
	if resMerc.Result != 0 {
		descrError := fmt.Sprintf("сервер ККТ меркурий не работает по причине %v", resMerc.Description)
		logsmy.LogginInFile(descrError)
		return "", resMerc, fmt.Errorf(descrError)
	}
	if sessionkey == "" {
		answer, err := opensession(connectionParams, authParams)
		if err != nil {
			descrError := "ошибка при подключении к ккт меркурий"
			logsmy.LogginInFile(descrError)
			return "", resMerc, fmt.Errorf(descrError)
		}
		err = json.Unmarshal(answer, &resMerc)
		if err != nil {
			descrError := fmt.Sprintf("ошибка при разборе ответа %v от ккт меркурий", answer)
			logsmy.LogginInFile(descrError)
			return "", resMerc, fmt.Errorf(descrError)
		}

		if resMerc.Result != 0 || resMerc.SessionKey == "" {
			descrError := "ошибка при подключении к ккт меркурий"
			logsmy.LogginInFile(descrError)
			err = fmt.Errorf(resMerc.Description)
			if !emulationParams.Emulation {
				return "", resMerc, fmt.Errorf(descrError)
			} else {
				testnomsessii = testnomsessii + 1
				resMerc.SessionKey = "эмуляция" + strconv.Itoa(testnomsessii)
			}
		}
		sessionkey = resMerc.SessionKey
		//defer closesession(ipktt, port, sessionkey, loginfo)
	}
	answerbyteKKT, errStatusKKT := getStatusKKT(connectionParams, sessionkey)
	if errStatusKKT != nil {
		descrError := "ошибка получения статуса ккт меркурий"
		logsmy.LogginInFile(descrError)
		Closesession(connectionParams, &sessionkey)
		return "", resMerc, fmt.Errorf(descrError)
	}
	errUnmarshKKT := json.Unmarshal(answerbyteKKT, &resMerc)
	if errUnmarshKKT != nil {
		descrError := fmt.Sprintf("ошибка распаковки ответа %v ккт меркурий", string(answerbyteKKT))
		logsmy.LogginInFile(descrError)
		Closesession(connectionParams, &sessionkey)
		return "", resMerc, fmt.Errorf(descrError)
	}
	if resMerc.Result != 0 {
		descrError := fmt.Sprintf("ккт меркурий не работает по причине %v", resMerc.Description)
		logsmy.LogginInFile(descrError)
		if !emulationParams.Emulation {
			Closesession(connectionParams, &sessionkey)
			return "", resMerc, fmt.Errorf(resMerc.Description)
		}
	}
	return sessionkey, resMerc, nil
} //checkStatsuConnectionKKT

func DissconnectMecruriy(connectionParams TConnectionParams, sessionkey string) (string, error) {
	var resMerc TAnswerMercur
	if sessionkey != "" {
		Closesession(connectionParams, &sessionkey)
	}
	jsonmerc := []byte("{\"command\":\"ClosePorts\"}")
	buffAnsw, err := sendCommandTCPMerc(jsonmerc, connectionParams)
	if err != nil {
		descrError := fmt.Sprintf("ошибка (%v) закрытия всех не активных портов для меркурия", err)
		return descrError, err
	}
	err = json.Unmarshal(buffAnsw, &resMerc)
	if err != nil {
		descrError := fmt.Sprintf("ошибка (%v) маршалинга результата закрытия закрытия всех не активных портов для меркурия", err)
		return descrError, err
	}
	if resMerc.Result != 0 {
		descrError := fmt.Sprintf("ошибка (%v) закрытия всех не активных портов для меркурий", resMerc.Description)
		err = fmt.Errorf(resMerc.Description)
		return descrError, err
	}
	return "", nil
}

func BreakAndClearProccessOfMarks(connectionParams TConnectionParams, sessionkey string, authParams TAuthParams) (string, error) {
	desckErrorBreak, errBreek := BreakProcCheckOfMark(connectionParams, sessionkey, authParams)
	desckErrorBreakClear, errClear := ClearTablesOfMarks(connectionParams, sessionkey, authParams)
	err := errors.Join(errBreek, errClear)
	return desckErrorBreak + desckErrorBreakClear, err
}

func BreakProcCheckOfMark(connectionParams TConnectionParams, sessionkey string, authParams TAuthParams) (string, error) {
	var resMerc TAnswerMercur
	if sessionkey == "" {
		answer, err := opensession(connectionParams, authParams)
		if err != nil {
			descrError := "ошибка при подключении к ккт меркурий"
			return descrError, err
		}
		err = json.Unmarshal(answer, &resMerc)
		if err != nil {
			descrError := "ошибка при подключении к ккт меркурий"
			return descrError, err
		}
		if resMerc.Result != 0 || resMerc.SessionKey == "" {
			descrError := "ошибка при подключении к ккт меркурий"
			err = fmt.Errorf(resMerc.Description)
			return descrError, err
		}
		sessionkey = resMerc.SessionKey
		defer Closesession(connectionParams, &sessionkey)
	}
	jsonmerc := []byte(fmt.Sprintf("{\"sessionKey\":\"%v\", \"command\":\"AbortMarkingCodeChecking\"}", sessionkey))
	buffAnsw, err := sendCommandTCPMerc(jsonmerc, connectionParams)
	if err != nil {
		descrError := "ошибка прерывания проверки марок"
		return descrError, err
	}
	err = json.Unmarshal(buffAnsw, &resMerc)
	if err != nil {
		descrError := "ошибка прерывания проверки марок"
		return descrError, err
	}
	descrError := resMerc.Description
	return descrError, nil
} //breakProcCheckOfMark

func ClearTablesOfMarks(connectionParams TConnectionParams, sessionkey string, authParams TAuthParams) (string, error) {
	var resMerc TAnswerMercur
	if sessionkey == "" {
		answer, err := opensession(connectionParams, authParams)
		if err != nil {
			descrError := "ошибка при подключении к ккт меркурий"
			return descrError, err
		}
		err = json.Unmarshal(answer, &resMerc)
		if err != nil {
			descrError := "ошибка при подключении к ккт меркурий"
			return descrError, err
		}
		if resMerc.Result != 0 || resMerc.SessionKey == "" {
			descrError := "ошибка при подключении к ккт меркурий"
			err = fmt.Errorf(resMerc.Description)
			return descrError, err
		}
		sessionkey = resMerc.SessionKey
		defer Closesession(connectionParams, &sessionkey)
	}
	jsonmerc := []byte(fmt.Sprintf("{\"sessionKey\":\"%v\", \"command\":\"ClearMarkingCodeValidationTable\"}", sessionkey))
	buffAnsw, err := sendCommandTCPMerc(jsonmerc, connectionParams)
	if err != nil {
		descrError := "ошибка очистки таблицы марок"
		return descrError, err
	}
	err = json.Unmarshal(buffAnsw, &resMerc)
	if err != nil {
		descrError := "ошибка очистки таблицы марок"
		return descrError, err
	}
	descrError := resMerc.Description
	return descrError, nil
} //ClearTablesOfMarks

// ////////////////////
func getStatusServerKKT(connectionParams TConnectionParams) ([]byte, error) {
	jsonmerc := []byte("{\"command\":\"GetDriverInfo\"}")
	buffAnsw, err := sendCommandTCPMerc(jsonmerc, connectionParams)
	if err != nil {
		return nil, err
	}
	return buffAnsw, nil
} //getStatusServerKKT

/*func getInfoKKT(ipktt string, port int, sessionkey string) ([]byte, error) {
	jsonmerc := []byte(fmt.Sprintf("{\"sessionKey\":\"%v\", \"command\":\"GetCommonInfo\"}", sessionkey))
	buffAnsw, err := sendCommandTCPMerc(jsonmerc, ipktt, port)
	if err != nil {
		return nil, err
	}
	return buffAnsw, nil
} //getStatusKKT*/

func getJSONBeginProcessMarkCheck(isReturn bool, mark string, measureunit int, sessionkey string) ([]byte, error) {
	plannedStatus := 1
	if isReturn {
		plannedStatus = 3
	}
	jsonmerc := []byte(fmt.Sprintf("{\"sessionKey\":\"%v\", \"command\":\"CheckMarkingCode\", \"mc\":\"%v\", \"plannedStatus\": %v, \"qty\": 10000, \"measureUnit\": %v}", sessionkey, mark, plannedStatus, measureunit))
	return jsonmerc, nil
}

func SendCheckOfMark(connectionParams TConnectionParams, sessionkey string, isReturn bool, mark string, measureunit int) ([]byte, error) {
	jsonBeginProcMark, err := getJSONBeginProcessMarkCheck(isReturn, mark, measureunit, sessionkey)
	if err != nil {
		return nil, err
	}
	return sendCommandTCPMerc(jsonBeginProcMark, connectionParams)
}

func GetStatusOfChecking(connectionParams TConnectionParams, sessionkey string) ([]byte, error) {
	jsonOfStatusProcMark := []byte(fmt.Sprintf("{\"sessionKey\":\"%v\", \"command\":\"GetMarkingCodeCheckResult\"}", sessionkey))
	return sendCommandTCPMerc(jsonOfStatusProcMark, connectionParams)
}

func AcceptMark(connectionParams TConnectionParams, sessionkey string) ([]byte, error) {
	jsonOfStatusProcMark := []byte(fmt.Sprintf("{\"sessionKey\":\"%v\", \"command\":\"AcceptMarkingCode\"}", sessionkey))
	return sendCommandTCPMerc(jsonOfStatusProcMark, connectionParams)
}

func getStatusKKT(connectionParams TConnectionParams, sessionkey string) ([]byte, error) {
	jsonmerc := []byte(fmt.Sprintf("{\"sessionKey\":\"%v\", \"command\":\"GetStatus\"}", sessionkey))
	buffAnsw, err := sendCommandTCPMerc(jsonmerc, connectionParams)
	if err != nil {
		return nil, err
	}
	return buffAnsw, nil
} //getStatusKKT

func convertToMercHeader(checkData consttypes.TCheckData) (TMercOpenCheck, error) {
	var checheaderkmerc TMercOpenCheck
	checheaderkmerc.Command = "OpenCheck"
	checheaderkmerc.CheckType = 0
	if checkData.Type == "retrun" {
		checheaderkmerc.CheckType = 1
	}
	if checkData.TaxationType == "" {
		return checheaderkmerc, errors.New("не указан тип налогообложения")
	}
	if checkData.TaxationType == "osn" {
		checheaderkmerc.TaxSystem = 0
	} else if checkData.TaxationType == "usnIncome" {
		checheaderkmerc.TaxSystem = 1
	} else if checkData.TaxationType == "usnIncomeOutcome" {
		checheaderkmerc.TaxSystem = 2
	} else if checkData.TaxationType == "esn" {
		checheaderkmerc.TaxSystem = 4
	} else if checkData.TaxationType == "patent" {
		checheaderkmerc.TaxSystem = 5
	}
	checheaderkmerc.CashierInfo.CashierName = checkData.Cashier
	return checheaderkmerc, nil
} //convertAtolToMercHeader

func convertMercCloseCheck(CheckData consttypes.TCheckData) TMercCloseCheck {
	var checclosekmerc TMercCloseCheck
	checclosekmerc.Command = "CloseCheck"
	for _, payments := range CheckData.Payments {
		if payments.Type == "cash" {
			checclosekmerc.Payment.Cash = payments.Sum
		}
		if payments.Type == "card" {
			checclosekmerc.Payment.Ecash = payments.Sum
		}
		if payments.Type == "prepaid" {
			checclosekmerc.Payment.Prepayment = payments.Sum
		}
		if payments.Type == "credit" {
			checclosekmerc.Payment.Credit = payments.Sum
		}
		if payments.Type == "other" {
			checclosekmerc.Payment.Consideration = payments.Sum
		}
	}
	return checclosekmerc
} //convertMercCloseCheck

func convertTaxNDSCode(taxType string) int {
	resTaxNDS := 6
	if taxType == "vat0" {
		resTaxNDS = 5
	}
	if taxType == "vat10" {
		resTaxNDS = 2
	}
	if taxType == "vat20" {
		resTaxNDS = 1
	}
	if taxType == "vat110" {
		resTaxNDS = 4
	}
	if taxType == "vat120" {
		resTaxNDS = 3
	}
	return resTaxNDS
} //convertTaxNDSCode

func convertPlannedStatusOfmc(status string) int {
	resStatus := 255
	if status == "itemPieceSold" {
		resStatus = 1
	}
	if status == "itemDryForSale" {
		resStatus = 2
	}
	if status == "itemPieceReturn" {
		resStatus = 3
	}
	if status == "itemDryReturn" {
		resStatus = 4
	}
	return resStatus
} //convertPlannedStatusOfmc

type TMercPosition struct {
	SessionKey      string                  `json:"sessionKey"`
	Command         string                  `json:"command"`
	MarkingCode     string                  `json:"markingCode,omitempty"`
	McInfo          *consttypes.TMcInfoMerc `json:"mcInfo,omitempty"`
	ProductName     string                  `json:"productName"`
	Qty             int                     `json:"qty"`
	MeasureUnit     int                     `json:"measureUnit"`
	TaxCode         int                     `json:"taxCode"`
	PaymentFormCode int                     `json:"paymentFormCode"`
	ProductTypeCode int                     `json:"productTypeCode"`
	Price           int                     `json:"price"`
	Sum             int                     `json:"sum,omitempty"`
	Agent           *TAgentMerc             `json:"agent,omitempty"`
}

func convertToMercPos(pos consttypes.TCheckItem) (TMercPosition, error) {
	var mercPos TMercPosition
	mercPos.Command = "AddGoods"
	mercPos.ProductName = pos.Name
	mercPos.Qty = pos.Quantity
	mercPos.MeasureUnit = 0 //еденица измерения 0 - штука
	mercPos.TaxCode = 6     // ставка НДС 6 - НДС не облагается
	if pos.TaxNDS != "" {
		mercPos.TaxCode = convertTaxNDSCode(pos.TaxNDS)
	}
	//mercPos.PaymentFormCode = convertSposRasch(pos.PaymentMethod)
	//mercPos.ProductTypeCode = convertPredmRash(pos.PaymentObject)
	mercPos.PaymentFormCode = 4 // форма расчета 4 - полный расчет
	mercPos.ProductTypeCode = 1 // 1 - товар
	mercPos.Price = pos.Price
	if pos.McInfo != nil {
		mercPos.McInfo = &consttypes.TMcInfoMerc{
			Mc:             pos.McInfo.Mc,
			PlannedStatus:  pos.McInfo.PlannedStatus,
			ProcessingMode: pos.McInfo.ProcessingMode,
		}
	}
	return mercPos, nil
} //convertToMercPos

func sendCommandTCPMerc(bytesjson []byte, connectionParams TConnectionParams) ([]byte, error) {
	var buffAnsw []byte
	logsmy.LogginInFile(string(bytesjson))
	conn, err := net.DialTimeout("tcp", connectionParams.IPAddress+":"+strconv.Itoa(connectionParams.Port), 5*time.Second)
	if err != nil {
		descError := fmt.Sprintf("error: ошибка рукопожатия tcp %v\r\n", err)
		descError = descError + fmt.Sprintln("сервер ККТ не отвечает ККТ")
		logsmy.Logsmap[consttypes.LOGERROR].Println(descError)
		return buffAnsw, err
	}
	defer conn.Close()
	jsonBytes := bytesjson
	lenTCP := int32(len(jsonBytes))
	bytesLen := make([]byte, 4)
	bytesLen[3] = byte(lenTCP >> 0)
	bytesLen[2] = byte(lenTCP >> (1 * 8))
	bytesLen[1] = byte(lenTCP >> (2 * 8))
	bytesLen[0] = byte(lenTCP >> (3 * 8))
	var bufTCP bytes.Buffer
	_, err = bufTCP.Write(bytesLen)
	if err != nil {
		descError := fmt.Sprintf("error: ошибка записи в буфер данных длины пакета: %v\r\n", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(descError)
		return buffAnsw, err
	}
	bufTCP.Write(jsonBytes)
	bufTCPReader := bytes.NewReader(bufTCP.Bytes())
	buffAnsw = make([]byte, 1024)
	var n int
	_, err = mustCopy(conn, bufTCPReader)
	if err != nil {
		descError := fmt.Sprintf("error: ошибка отправка tcp заароса серверу Мекрурия %v\r\n", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(descError)
		return buffAnsw, err
	}
	n, err = conn.Read(buffAnsw)
	if err != nil {
		descError := fmt.Sprintf("error: ошибка получения ответа от сервера Меркурия  %v \r\n", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(descError)
		return buffAnsw, err
	}
	logsmy.LogginInFile(string(buffAnsw))
	return buffAnsw[4:n], nil
} //sendCommandTCPMerc

func mustCopy(dst io.Writer, src io.Reader) (int64, error) {
	count, err := io.Copy(dst, src)
	if err != nil {
		descError := fmt.Sprintf("ошибка копирования %v\r\n", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(descError)
	}
	return count, err
} //mustCopy

func opensession(connectionParams TConnectionParams, authParams TAuthParams) ([]byte, error) {
	var jsonmerc []byte
	//jsonmerc := []byte(fmt.Sprintf("{\"sessionKey\":\"null\", \"command\":\"OpenSession\", \"portName\":\"COM%v\"}", comport))
	//jsonmerc := []byte(fmt.Sprintf("{\"sessionKey\":null, \"command\":\"OpenSession\", \"portName\":\"COM%v\"}", comport))
	if (authParams.UserInt != 0) || (authParams.PasswUser != "") {
		jsonmerc = []byte(fmt.Sprintf("{\"sessionKey\":null, \"command\":\"OpenSession\", \"portName\":\"COM%v\", \"model\":\"185F\", \"userNumber\": %v,\"userPassword\": \"%v\", \"debug\": true, \"logPath\": \"c:\\\\logs\\\\\"}", connectionParams.ComPort, authParams.UserInt, authParams.PasswUser))
	} else {
		jsonmerc = []byte(fmt.Sprintf("{\"sessionKey\":null, \"command\":\"OpenSession\", \"portName\":\"COM%v\", \"model\":\"185F\", \"debug\": true, \"logPath\": \"c:\\\\logs\\\\\"}", connectionParams.ComPort))
	}

	buffAnsw, err := sendCommandTCPMerc(jsonmerc, connectionParams)
	if err != nil {
		descError := fmt.Sprintf("ошибка (%v) открытия сессии для кассы меркурий", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(descError)
		return buffAnsw, err
	}
	return buffAnsw, nil
} //opensession

func checkOpenShift(connectionParams TConnectionParams, openShiftIfClose bool, kassir string, sessionkey string, authParams TAuthParams, emulationParams consttypes.TEmulationParams) (bool, error) {
	logsmy.LogginInFile("вызов checkOpenShift")
	logsmy.LogginInFile(fmt.Sprintf("checkOpenShift openShiftIfClose: %v, kassir: %v, sessionkey: %v", openShiftIfClose, kassir, sessionkey))
	sessionkey, merckAnswer, err := checkStatsuConnectionKKT(connectionParams, sessionkey, authParams, emulationParams)
	if err != nil {
		return false, err
	}
	if merckAnswer.ShiftInfo.IsOpen {
		return true, nil
	}
	if !openShiftIfClose {
		return false, nil
	}
	if merckAnswer.ShiftInfo.Is24Expired {
		logsmy.LogginInFile("смена превысила 24 часа")
		err = fmt.Errorf("смена превысила 24 часа")
		return false, err
	}
	jsonmerc := []byte(fmt.Sprintf("{\"sessionKey\":\"%v\", \"command\":\"OpenShift\", \"cashierName\":\"%v\"}", sessionkey, kassir))
	logsmy.LogginInFile(string(jsonmerc))
	buffAnsw, err := sendCommandTCPMerc(jsonmerc, connectionParams)
	if err != nil {
		descError := fmt.Sprintf("ошибка (%v) открытия смены для кассы меркурий", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(descError)
		return false, err
	}
	var resMerc TAnswerMercur
	err = json.Unmarshal(buffAnsw, &resMerc)
	if err != nil {
		descError := fmt.Sprintf("ошибка (%v) маршалинга результата открытия смены для кассы меркурий", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(descError)
		return false, err
	}
	if resMerc.Result != 0 {
		descError := fmt.Sprintf("ошибка (%v) открытия смены для кассы меркурий", resMerc.Description)
		logsmy.Logsmap[consttypes.LOGERROR].Println(descError)
		return false, err

	}
	return true, nil
} //checkOpenShift

func opencheck(connectionParams TConnectionParams, headercheckjson []byte) ([]byte, error) {
	buffAnsw, err := sendCommandTCPMerc(headercheckjson, connectionParams)
	if err != nil {
		descError := fmt.Sprintf("ошибка (%v) открытия чека для кассы меркурий", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(descError)
		return buffAnsw, err
	}
	return buffAnsw, nil
} //opencheck

func addpos(connectionParams TConnectionParams, posjson []byte) ([]byte, error) {
	buffAnsw, err := sendCommandTCPMerc(posjson, connectionParams)
	if err != nil {
		descError := fmt.Sprintf("ошибка (%v) добавления позиции для кассы меркурий", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(descError)
		return buffAnsw, err
	}
	return buffAnsw, nil
}

func closecheck(connectionParams TConnectionParams, forclosedatamerc []byte) ([]byte, error) {
	buffAnsw, err := sendCommandTCPMerc(forclosedatamerc, connectionParams)
	if err != nil {
		descError := fmt.Sprintf("ошибка (%v) закрытия чека для кассы меркурий", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(descError)
		return buffAnsw, err
	}
	return buffAnsw, nil
} //closecheck

func cancelcheck(connectionParams TConnectionParams, sessionkey *string) ([]byte, error) {
	jsonmerc := []byte(fmt.Sprintf("{\"sessionKey\":\"%v\", \"command\":\"ResetCheck\"}", *sessionkey))
	buffAnsw, err := sendCommandTCPMerc(jsonmerc, connectionParams)
	if err != nil {
		descError := fmt.Sprintf("ошибка (%v) отмены чека для кассы меркурий", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(descError)
		return buffAnsw, err
	}
	return buffAnsw, nil
} //closecheck

func Closesession(connectionParams TConnectionParams, sessionkey *string) (string, error) {
	var resMerc TAnswerMercur
	jsonmerc := []byte(fmt.Sprintf("{\"sessionKey\":\"%v\", \"command\":\"CloseSession\"}", *sessionkey))
	buffAnsw, err := sendCommandTCPMerc(jsonmerc, connectionParams)
	*sessionkey = ""
	if err != nil {
		descrError := fmt.Sprintf("ошибка (%v) закрытия сессии для кассы меркурий", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(descrError)
		return descrError, err
	}
	err = json.Unmarshal(buffAnsw, &resMerc)
	if err != nil {
		descrError := fmt.Sprintf("ошибка (%v) маршалинга результата закрытия сессии для кассы меркурий", err)
		logsmy.Logsmap[consttypes.LOGERROR].Println(descrError)
		return descrError, err
	}
	if resMerc.Result != 0 {
		descrError := fmt.Sprintf("ошибка (%v) закрытия сессии для кассы меркурий", resMerc.Description)
		logsmy.Logsmap[consttypes.LOGERROR].Println(descrError)
		err = fmt.Errorf(resMerc.Description)
		return descrError, err
	}
	return "", nil
} //closesession

// NewDefaultConnectionParams возвращает TConnectionParams с значениями по умолчанию
func NewDefaultConnectionParams() TConnectionParams {
	return TConnectionParams{
		IPAddress: "localhost", // локальный хост по умолчанию
		Port:      50009,       // предполагаемый стандартный порт
		ComPort:   1,           // COM1 по умолчанию
	}
}

// NewDefaultAuthParams возвращает TAuthParams с значениями по умолчанию
func NewDefaultAuthParams() TAuthParams {
	return TAuthParams{
		UserInt:   0,  // Значение по умолчанию для UserInt
		PasswUser: "", // Пустая строка как значение по умолчанию для PasswUser
	}
}

func checkContainsMarks(checkData consttypes.TCheckData) bool {
	for _, item := range checkData.TableData {
		if item.Mark != "" {
			return true
		}
	}
	return false
}

func checkAndRunsCheckingMarksByCheck(checkData *consttypes.TCheckData, connectionParams TConnectionParams, sessionkey string, authParams TAuthParams, emulationParams consttypes.TEmulationParams) error {
	logsmy.LogginInFile("ищем марки в чеке")
	logsmy.LogginInFile(fmt.Sprintf("checkAndRunsCheckingMarksByCheck checkData: %v, sessionkey: %v, authParams: %v, emulationParams: %v", checkData, sessionkey, authParams, emulationParams))
	for _, item := range checkData.TableData {
		if item.Mark == "" {
			continue
		}
		item.McInfo = &consttypes.TMcInfoMerc{}
		logsmy.LogginInFile(fmt.Sprintf("checkAndRunsCheckingMarksByCheck найдена марка: %v", item.Mark))
		currMarkBase64 := item.Mark
		isReturn := false
		if checkData.Type == "return" {
			isReturn = true
		}
		imcResultCheckin, errproc := runProcessCheckMark(connectionParams, sessionkey, isReturn, currMarkBase64, 0, 0, emulationParams)
		if errproc != nil {
			logsmy.Logsmap[consttypes.LOGERROR].Println(fmt.Sprintf("ошибка (%v) при проверке марки %v", errproc, currMarkBase64))
			return errproc
		}
		item.McInfo.Mc = imcResultCheckin.Mc
		item.McInfo.PlannedStatus = imcResultCheckin.PlannedStatus
		item.McInfo.ProcessingMode = imcResultCheckin.ProcessingMode
	}
	return nil
}

func runProcessCheckMark(connectionParams TConnectionParams, sessionkey string, isReturn bool, mark string, countOfMaxAttempts int, pauseOfMarksMistake int, emulationParams consttypes.TEmulationParams) (consttypes.TMcInfoMerc, error) {
	var countAttempts int
	var imcResultCheckin consttypes.TMcInfoMerc

	logsmy.LogginInFile("начало процедуры runProcessCheckMark")
	//посылаем запрос на проверку марки
	var err error
	if pauseOfMarksMistake == 0 {
		pauseOfMarksMistake = 1
	}
	if countOfMaxAttempts == 0 {
		countOfMaxAttempts = 5
	}
	var mercAnswerBeginCheckMark TAnswerMercur
	var resMercAnswerBytes []byte
	resMercAnswerBytes, err = SendCheckOfMark(connectionParams, sessionkey, isReturn, mark, 0)
	if err != nil {
		errorDescr := fmt.Sprintf("ошибка (%v) запуска проверки марки %v", err, mark)
		logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
		return consttypes.TMcInfoMerc{}, errors.New(errorDescr)
	}
	err = json.Unmarshal(resMercAnswerBytes, &mercAnswerBeginCheckMark)
	if err != nil {
		errorDescr := fmt.Sprintf("ошибка (%v) парсинга (%v) ответа проверки марки %v", err, resMercAnswerBytes, mark)
		logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
		return consttypes.TMcInfoMerc{}, errors.New(errorDescr)
	}
	if mercAnswerBeginCheckMark.Result != 0 && !*&emulationParams.Emulation {
		descError := fmt.Sprintf("ошибка (%v) запуска проверки марки %v", mercAnswerBeginCheckMark.Description, mark)
		logsmy.Logsmap[consttypes.LOGERROR].Println(descError)
		return consttypes.TMcInfoMerc{}, errors.New(descError)
	}
	var mercurAnswerOfGetStatusMark TAnswerMercur
	mercurAnswerOfGetStatusMark.IsCompleted = false
	for countAttempts = 0; countAttempts < countOfMaxAttempts; countAttempts++ {
		resMercAnswerBytes, err = GetStatusOfChecking(connectionParams, sessionkey)
		if err != nil {
			errorDescr := fmt.Sprintf("ошибка (%v) получения статуса проверки марки %v", err, mark)
			logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
			return consttypes.TMcInfoMerc{}, errors.New(errorDescr)
		}
		err = json.Unmarshal(resMercAnswerBytes, &mercurAnswerOfGetStatusMark)
		if err != nil {
			errorDescr := fmt.Sprintf("ошибка (%v) парсинга (%v) ответа проверки марки %v", err, resMercAnswerBytes, mark)
			logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
			return consttypes.TMcInfoMerc{}, errors.New(errorDescr)
		}
		if mercurAnswerOfGetStatusMark.Result != 0 && !emulationParams.Emulation {
			descError := fmt.Sprintf("ошибка (%v) запуска проверки марки %v", mercurAnswerOfGetStatusMark.Description, mark)
			logsmy.Logsmap[consttypes.LOGERROR].Println(descError)
			return consttypes.TMcInfoMerc{}, errors.New(descError)
		}
		if mercurAnswerOfGetStatusMark.Result != 0 && emulationParams.Emulation {
			if countAttempts > countOfMaxAttempts-2 { //эмулируем задержку получения марки
				mercurAnswerOfGetStatusMark.IsCompleted = true
			}
		}
		if mercurAnswerOfGetStatusMark.IsCompleted {
			break
		}
		//пауза в 1 секунду
		logsmy.Logsmap[consttypes.LOGINFO_WITHSTD].Printf("попытка %v из %v получения статуса марки", countAttempts+1, countOfMaxAttempts)
		duration := time.Second
		time.Sleep(duration)
	}
	if (countAttempts == countOfMaxAttempts) && !mercurAnswerOfGetStatusMark.IsCompleted {
		errorDescr := fmt.Sprintf("ошибка проверки марки %v - превышено число %v попыток", mark, countOfMaxAttempts)
		logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
		return consttypes.TMcInfoMerc{}, errors.New(errorDescr)
	}

	//принимаем марку
	var resOfChecking string
	var mercurAnswerOfAcceptMark TAnswerMercur
	resMercAnswerBytes, err = AcceptMark(connectionParams, sessionkey)
	if err != nil {
		errorDescr := fmt.Sprintf("ошибка (%v) принятия марки %v", err, mark)
		logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
		return consttypes.TMcInfoMerc{}, errors.New(errorDescr)
	}
	err = json.Unmarshal(resMercAnswerBytes, &mercurAnswerOfAcceptMark)
	if err != nil {
		errorDescr := fmt.Sprintf("ошибка (%v) парсинга (%v) ответа принятия марки %v", err, resMercAnswerBytes, mark)
		logsmy.Logsmap[consttypes.LOGERROR].Println(errorDescr)
		return consttypes.TMcInfoMerc{}, errors.New(errorDescr)
	}
	resOfChecking = mercurAnswerOfAcceptMark.Description
	if mercurAnswerOfAcceptMark.Result != 0 && !emulationParams.Emulation {
		logsmy.LogginInFile(fmt.Sprintf("ошибка (%v) принятия марки %v", resOfChecking, mark))
		return consttypes.TMcInfoMerc{}, errors.New(resOfChecking)
	}
	var plannedStatus int
	plannedStatus = 1
	if isReturn {
		plannedStatus = 3
	}
	imcResultCheckin.Mc = mark
	imcResultCheckin.PlannedStatus = plannedStatus
	imcResultCheckin.ProcessingMode = 0
	logsmy.LogginInFile("конец процедуры runProcessCheckMark без ошибки")
	return imcResultCheckin, nil
} //runProcessCheckMark
