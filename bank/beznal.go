package main

import (
	"fmt"

	"github.com/go-ole/go-ole"
	"github.com/go-ole/go-ole/oleutil"
)

// PayMoney выполняет оплату по безналу
func PayMoney(amount int) (string, int64, error) {
	ole.CoInitialize(0)
	defer ole.CoUninitialize()

	unknown, err := oleutil.CreateObject("SBRFSRV.Server")
	if err != nil {
		return "", -1, fmt.Errorf("Error creating COM object: %v", err)
	}
	defer unknown.Release()

	sber, err := unknown.QueryInterface(ole.IID_IDispatch)
	if err != nil {
		return "", -1, fmt.Errorf("Error querying interface: %v", err)
	}
	defer sber.Release()

	// Вызов Clear
	oleutil.CallMethod(sber, "Clear")

	// Установка параметра Amount
	oleutil.CallMethod(sber, "SParam", "Amount", amount)

	// Вызов NFun
	result, err := oleutil.CallMethod(sber, "NFun", 4000)
	if err != nil {
		return "", -1, fmt.Errorf("Error calling NFun: %v", err)
	}

	if result.Val == 0 {
		// Получение параметра Cheque
		cheque, err := oleutil.CallMethod(sber, "GParamString", "Cheque")
		if err != nil {
			return "", result.Val, fmt.Errorf("Error getting Cheque parameter: %v", err)
		}

		// Очистка параметров
		oleutil.CallMethod(sber, "Clear")

		return cheque.ToString(), result.Val, nil
	} else {
		// Очистка параметров
		oleutil.CallMethod(sber, "Clear")

		return "", result.Val, fmt.Errorf("Error generating receipt. Код ошибки: %v", result.Val)
	}
}

// ReturnMoney выполняет возврат по безналу
func ReturnMoney(amount int) (string, int64, error) {
	ole.CoInitialize(0)
	defer ole.CoUninitialize()

	unknown, err := oleutil.CreateObject("SBRFSRV.Server")
	if err != nil {
		return "", -1, fmt.Errorf("Error creating COM object: %v", err)
	}
	defer unknown.Release()

	sber, err := unknown.QueryInterface(ole.IID_IDispatch)
	if err != nil {
		return "", -1, fmt.Errorf("Error querying interface: %v", err)
	}
	defer sber.Release()

	// Вызов Clear
	oleutil.CallMethod(sber, "Clear")

	// Установка параметра Amount
	oleutil.CallMethod(sber, "SParam", "Amount", amount)

	// Вызов NFun
	result, err := oleutil.CallMethod(sber, "NFun", 4002)
	if err != nil {
		return "", -1, fmt.Errorf("Error calling NFun: %v", err)
	}

	if result.Val == 0 {
		// Получение параметра Cheque
		cheque, err := oleutil.CallMethod(sber, "GParamString", "Cheque")
		if err != nil {
			return "", result.Val, fmt.Errorf("Error getting Cheque parameter: %v", err)
		}

		// Очистка параметров
		oleutil.CallMethod(sber, "Clear")

		return cheque.ToString(), result.Val, nil
	} else {
		// Очистка параметров
		oleutil.CallMethod(sber, "Clear")

		return "", result.Val, fmt.Errorf("Error generating receipt")
	}
}

// CloseShiftTerminal закрывает смену терминала
func CloseShiftTerminal() (string, int64, error) {
	ole.CoInitialize(0)
	defer ole.CoUninitialize()

	unknown, err := oleutil.CreateObject("SBRFSRV.Server")
	if err != nil {
		return "", -1, fmt.Errorf("Error creating COM object: %v", err)
	}
	defer unknown.Release()

	sber, err := unknown.QueryInterface(ole.IID_IDispatch)
	if err != nil {
		return "", -1, fmt.Errorf("Error querying interface: %v", err)
	}
	defer sber.Release()

	// Вызов Clear
	oleutil.CallMethod(sber, "Clear")

	// Вызов NFun
	result, err := oleutil.CallMethod(sber, "NFun", 6000)
	if err != nil {
		return "", -1, fmt.Errorf("Error calling NFun: %v", err)
	}

	if result.Val == 0 {
		// Получение параметра Cheque
		cheque, err := oleutil.CallMethod(sber, "GParamString", "Cheque")
		if err != nil {
			return "", result.Val, fmt.Errorf("Error getting Cheque parameter: %v", err)
		}

		// Очистка параметров
		oleutil.CallMethod(sber, "Clear")

		return cheque.ToString(), result.Val, nil
	} else {
		// Очистка параметров
		oleutil.CallMethod(sber, "Clear")

		return "", result.Val, fmt.Errorf("Error closing shift")
	}
}

// CancelMoney выполняет отмену по безналу
func CancelMoney(amount int) (string, int64, error) {
	ole.CoInitialize(0)
	defer ole.CoUninitialize()

	unknown, err := oleutil.CreateObject("SBRFSRV.Server")
	if err != nil {
		return "", -1, fmt.Errorf("Error creating COM object: %v", err)
	}
	defer unknown.Release()

	sber, err := unknown.QueryInterface(ole.IID_IDispatch)
	if err != nil {
		return "", -1, fmt.Errorf("Error querying interface: %v", err)
	}
	defer sber.Release()

	// Вызов Clear
	oleutil.CallMethod(sber, "Clear")

	// Установка параметра Amount
	oleutil.CallMethod(sber, "SParam", "Amount", amount)

	// Вызов NFun с кодом 6004
	result, err := oleutil.CallMethod(sber, "NFun", 6004)
	if err != nil {
		return "", -1, fmt.Errorf("Error calling NFun: %v", err)
	}

	if result.Val == 0 {
		// Получение параметра Cheque
		cheque, err := oleutil.CallMethod(sber, "GParamString", "Cheque")
		if err != nil {
			return "", result.Val, fmt.Errorf("Error getting Cheque parameter: %v", err)
		}

		// Очистка параметров
		oleutil.CallMethod(sber, "Clear")

		return cheque.ToString(), result.Val, nil
	} else {
		// Очистка параметров
		oleutil.CallMethod(sber, "Clear")

		return "", result.Val, fmt.Errorf("Error canceling operation. Код ошибки: %v", result.Val)
	}
}
