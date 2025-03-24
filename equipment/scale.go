package equipment

import (
	"fmt"

	"github.com/go-ole/go-ole"
	"github.com/go-ole/go-ole/oleutil"
)

// PayMoney выполняет оплату по безналу
func GetWeight() (string, error) {
	ole.CoInitialize(0)
	defer ole.CoUninitialize()

	unknown, err := oleutil.CreateObject("AddIn.Scale45")
	if err != nil {
		return "", fmt.Errorf("error creating COM object: %v", err)
	}
	defer unknown.Release()

	scale, err := unknown.QueryInterface(ole.IID_IDispatch)
	if err != nil {
		return "", fmt.Errorf("error querying interface: %v", err)
	}
	defer scale.Release()

	_, err = oleutil.PutProperty(scale, "PortNumber", 1)
	if err != nil {
		return "", fmt.Errorf("error setting PortNumber property: %v", err)
	}

	_, err = oleutil.PutProperty(scale, "BaudRate", 18)
	if err != nil {
		return "", fmt.Errorf("error setting BaudRate property: %v", err)
	}

	_, err = oleutil.PutProperty(scale, "Model", 38) //атол марта
	if err != nil {
		return "", fmt.Errorf("error setting Model property: %v", err)
	}

	resultOpen, err := oleutil.PutProperty(scale, "DeviceEnabled", true)
	if err != nil {
		return "", fmt.Errorf("error getting device enabled state: %v", err)
	}
	if resultOpen.Val != 0 {
		resultDescription, err := oleutil.GetProperty(scale, "ResultDescription")
		if err != nil {
			return "", fmt.Errorf("error getting result description: %v", err)
		}
		return "", fmt.Errorf("error getting device enabled state: %s", resultDescription.ToString())
	}

	// Вызов получить вес
	result, err := oleutil.CallMethod(scale, "ReadWeight")
	if err != nil {
		return "", fmt.Errorf("error calling ReadWeight: %v", err)
	}

	var weight *ole.VARIANT
	if result.Val == 0 {
		var err error
		// Получение параметра веса
		weight, err = oleutil.GetProperty(scale, "Weight")
		if err != nil {
			return "", fmt.Errorf("error getting weight parameter: %v", err)
		}

		return weight.ToString(), nil
	} else {
		// Очистка параметров
		resultDescription, err := oleutil.GetProperty(scale, "ResultDescription")
		if err != nil {
			return "", fmt.Errorf("error getting weight, error getting result description: %v", err)
		}
		return "", fmt.Errorf("error getting weight, result description: %s", resultDescription.ToString())
	}
}
