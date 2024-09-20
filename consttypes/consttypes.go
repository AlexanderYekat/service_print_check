package consttypes

import "os"

type TUserAttribute struct {
	AttrName  string `json:"attrName"`
	AttrValue string `json:"attrValue"`
}

type TClientInfo struct {
	EmailOrPhone string `json:"emailOrPhone"`
	Vatin        string `json:"vatin,omitempty"`
	Name         string `json:"name,omitempty"`
}

type TTaxNDS struct {
	Type string `json:"type,omitempty"`
}
type TProductCodesAtol struct {
	Undefined    string `json:"undefined,omitempty"` //32 символа только
	Code_EAN_8   string `json:"ean8,omitempty"`
	Code_EAN_13  string `json:"ean13,omitempty"`
	Code_ITF_14  string `json:"itf14,omitempty"`
	Code_GS_1    string `json:"gs10,omitempty"`
	Tag1305      string `json:"gs1m,omitempty"`
	Code_KMK     string `json:"short,omitempty"`
	Code_MI      string `json:"furs,omitempty"`
	Code_EGAIS_2 string `json:"egais20,omitempty"`
	Code_EGAIS_3 string `json:"egais30,omitempty"`
	Code_F_1     string `json:"f1,omitempty"`
	Code_F_2     string `json:"f2,omitempty"`
	Code_F_3     string `json:"f3,omitempty"`
	Code_F_4     string `json:"f4,omitempty"`
	Code_F_5     string `json:"f5,omitempty"`
	Code_F_6     string `json:"f6,omitempty"`
}

type TPayment struct {
	Type string `json:"type"` //cash - наличный расчет, card - безналичный расчет
	Sum  int    `json:"sum"`
}

type TGenearaPosAndTag11921191 struct {
	Type string `json:"type"`
}

type TAgentInfo struct {
	Agents []string `json:"agents"`
}
type TSupplierInfo struct {
	Vatin  string   `json:"vatin"`
	Name   string   `json:"name,omitempty"`
	Phones []string `json:"phones,omitempty"`
}

type TAnsweChekcMark struct {
	Ready bool `json:"ready"`
}

type TBeginTaskMarkCheck struct {
	Type   string     `json:"type"`
	Params TImcParams `json:"params"`
}

type TItemInfoCheckResult struct {
	ImcCheckFlag              bool `json:"imcCheckFlag"`
	ImcCheckResult            bool `json:"imcCheckResult"`
	ImcStatusInfo             bool `json:"imcStatusInfo"`
	ImcEstimatedStatusCorrect bool `json:"imcEstimatedStatusCorrect"`
	EcrStandAloneFlag         bool `json:"ecrStandAloneFlag"`
}

type TImcParams struct {
	ImcType             string                `json:"imcType"`
	Imc                 string                `json:"imc"`
	ItemEstimatedStatus string                `json:"itemEstimatedStatus"`
	ImcModeProcessing   int                   `json:"imcModeProcessing"`
	ImcBarcode          string                `json:"imcBarcode,omitempty"`
	ItemInfoCheckResult *TItemInfoCheckResult `json:"itemInfoCheckResult,omitempty"`
	ItemQuantity        float64               `json:"itemQuantity,omitempty"`
	ItemUnits           string                `json:"itemUnits,omitempty"`
	NotSendToServer     bool                  `json:"notSendToServer,omitempty"`
}

type TAnswerGetStatusOfShift struct {
	ShiftStatus TShiftStatus `json:"shiftStatus"`
}
type TShiftStatus struct {
	DocumentsCount int    `json:"documentsCount"`
	ExpiredTime    string `json:"expiredTime"`
	Number         int    `json:"number"`
	State          string `json:"state"`
}

// структура результата проверки информации о товаре
type TItemInfoCheckResultObject struct {
	ItemInfoCheckResult TItemInfoCheckResult `json:"itemInfoCheckResult"`
}

type TPartMerc struct {
	Numerator   int `json:"Numerator"`
	Denominator int `json:"denominator"`
}

type TMcInfoMerc struct {
	Mc             string     `json:"mc"`
	Ean            string     `json:"ean,omitempty"`
	ProcessingMode int        `json:"processingMode"`
	PlannedStatus  int        `json:"plannedStatus"`
	Part           *TPartMerc `json:"part,omitempty"`
}

// структура элемента чека
type TCheckItem struct {
	Name     string       `json:"name"`
	Quantity int          `json:"quantity"`
	Price    int          `json:"price"`
	Mark     string       `json:"mark,omitempty"`
	McInfo   *TMcInfoMerc `json:"mcInfo,omitempty"`
	TaxNDS   string       `json:"taxNDS,omitempty"`
}

// структура данных чека
type TCheckData struct {
	TableData    []TCheckItem `json:"tableData"`
	Cashier      string       `json:"cashier"`
	Type         string       `json:"type"`         //тип чека sell - продажа, return - возврат
	TaxationType string       `json:"taxationType"` //тип налогообложения osn - общая система налогообложения, usn_income - упрощенная система налогообложения (доход), usn_income_outcome - упрощенная система налогообложения (доход минус расход), envd - единый налог на вмененный доход, esn - единый сельскохозяйственный налог, patent - патентная система налогообложения
	Payments     []TPayment   `json:"payments"`     //список оплат cash - наличный расчет, card - безналичный расчет, both - наличный и безналичный расчет
}

// структура ответа на печать чека
type TPrintCheckResponse struct {
	Status      string `json:"status"`      //success - чек успешно отправлен на печать, error - ошибка при отправке чека на печать
	Message     string `json:"message"`     //сообщение об ошибке или успешном отправке чека на печать
	CheckNumber string `json:"checkNumber"` //номер чека
}

// параметры эмуляции
type TEmulationParams struct {
	Emulation                bool `json:"emulation"`                //эмуляция работы с кассы
	DontPrintRealForTest     bool `json:"dontPrintRealForTest"`     //не печатать реальный чек для тестирования
	EmulateMistakesOpenCheck bool `json:"emulateMistakesOpenCheck"` //эмулировать ошибки при открытии чека
}

// директории
var DIROFJSONS = ".\\jsons\\works\\"
var LOGSDIR = "./logs/"

// уровни логирования
const LOGINFO = "info"
const LOGINFO_WITHSTD = "info_std"
const LOGERROR = "error"
const LOGSKIP_LINES = "skip_line"
const LOGOTHER = "other"
const LOG_PREFIX = "TASKS"

// проверка наличия файла
func DoesFileExist(fullFileName string) (found bool, err error) {
	found = false
	if _, err = os.Stat(fullFileName); err == nil {
		// path/to/whatever exists
		found = true
	}
	return
}

// NewDefaultEmulationParams возвращает TEmulationParams с значениями по умолчанию
func NewDefaultEmulationParams() TEmulationParams {
	return TEmulationParams{
		Emulation:                false,
		DontPrintRealForTest:     false,
		EmulateMistakesOpenCheck: false,
	}
}
