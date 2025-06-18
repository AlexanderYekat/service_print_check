package main

import (
	"encoding/json"
	"fmt"
	"io/ioutil"
	"os"
	"path/filepath"
	"strconv"
	"strings"
)

type Result struct {
	Success    bool   `json:"Success"`
	Message    string `json:"Message"`
	Cheque     string `json:"Cheque"`
	CodeReturn int    `json:"CodeReturn,omitempty"`
}

func main() {
	baseDir, _ := filepath.Abs(filepath.Dir(os.Args[0]))
	tempDir := filepath.Join(baseDir, "temp")
	amountFile := filepath.Join(tempDir, "amount.txt")
	operationFile := filepath.Join(tempDir, "operation.txt")
	resultFile := filepath.Join(tempDir, "result.json")

	result := Result{Success: false, Message: "Начальное состояние", Cheque: ""}

	// Чтение типа операции
	operationBytes, err := ioutil.ReadFile(operationFile)
	if err != nil {
		result.Message = fmt.Sprintf("Файл типа операции не найден: %s", operationFile)
		writeResult(resultFile, result)
		os.Exit(1)
	}
	operation := strings.TrimSpace(strings.ToLower(string(operationBytes)))

	needAmount := operation == "pay" || operation == "return" || operation == "cancel"
	var amount int
	if needAmount {
		amountBytes, err := ioutil.ReadFile(amountFile)
		if err != nil {
			result.Message = fmt.Sprintf("Файл суммы не найден: %s", amountFile)
			writeResult(resultFile, result)
			os.Exit(1)
		}
		amountText := strings.TrimSpace(string(amountBytes))
		amount, err = strconv.Atoi(amountText)
		if err != nil {
			result.Message = fmt.Sprintf("Некорректная сумма в файле: %s", amountText)
			writeResult(resultFile, result)
			os.Exit(1)
		}
	}

	// Вызов нужной функции
	var cheque string
	var code int64
	switch operation {
	case "pay":
		cheque, code, err = PayMoney(amount)
		if err == nil {
			result.Success = true
			result.Message = "Операция успешно выполнена."
			result.Cheque = cheque
			result.CodeReturn = int(code)
		} else {
			result.Message = err.Error()
			result.CodeReturn = int(code)
		}
	case "return":
		cheque, code, err = ReturnMoney(amount)
		if err == nil {
			result.Success = true
			result.Message = "Операция успешно выполнена."
			result.Cheque = cheque
			result.CodeReturn = int(code)
		} else {
			result.Message = err.Error()
			result.CodeReturn = int(code)
		}
	case "cancel":
		cheque, code, err = CancelMoney(amount)
		if err == nil {
			result.Success = true
			result.Message = "Операция успешно выполнена."
			result.Cheque = cheque
			result.CodeReturn = int(code)
		} else {
			result.Message = err.Error()
			result.CodeReturn = int(code)
		}
	case "close_shift":
		cheque, code, err = CloseShiftTerminal()
		if err == nil {
			result.Success = true
			result.Message = "Операция успешно выполнена."
			result.Cheque = cheque
			result.CodeReturn = int(code)
		} else {
			result.Message = err.Error()
			result.CodeReturn = int(code)
		}
	default:
		result.Message = fmt.Sprintf("Неизвестная операция: %s", operation)
		result.CodeReturn = 1
	}

	writeResult(resultFile, result)
}

func writeResult(filename string, result Result) {
	data, _ := json.Marshal(result)
	_ = ioutil.WriteFile(filename, data, 0644)
}
