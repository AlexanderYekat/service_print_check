package equipment

import (
	"fmt"

	"github.com/go-ole/go-ole"
	"github.com/go-ole/go-ole/oleutil"
)

// GetWeight получает вес
func GetWeight(comPort int) (string, error) {
	ole.CoInitialize(0)
	defer ole.CoUninitialize()

	fmt.Println("Создание COM объекта...")
	unknown, err := oleutil.CreateObject("AddIn.Scale8")
	if err != nil {
		return "", fmt.Errorf("error creating COM object: %v", err)
	}
	defer unknown.Release()

	fmt.Println("Получение COM объекта...")
	scale, err := unknown.QueryInterface(ole.IID_IDispatch)
	if err != nil {
		return "", fmt.Errorf("error querying interface: %v", err)
	}
	defer scale.Release()

	DeviceCount, _ := oleutil.GetProperty(scale, "DeviceCount")

	//oleutil.CallMethod(scale, "LoadDevicesSettings")
	if DeviceCount.Val == 0 {
		_, err = oleutil.CallMethod(scale, "AddDevice")
	}

	fmt.Printf("Установка параметров COM порт %d...\n", comPort+1000)
	oleutil.PutProperty(scale, "PortNumber", comPort+1000)

	oleutil.PutProperty(scale, "BaudRate", 18)

	oleutil.PutProperty(scale, "Model", 38) //атол марта

	resultOpen, err := oleutil.PutProperty(scale, "DeviceEnabled", true)

	resultDescription, _ := oleutil.GetProperty(scale, "ResultDescription")
	fmt.Println("resultDescription:", resultDescription.Value())

	if resultOpen.Val != 0 {
		resultDescription, err := oleutil.GetProperty(scale, "ResultDescription")
		if err != nil {
			return "", fmt.Errorf("error getting result description: %v", err)
		}
		return "", fmt.Errorf("error getting device enabled state: %s", resultDescription.ToString())
	}
	resultOpenCheck, err := oleutil.GetProperty(scale, "DeviceEnabled")
	if err != nil {
		return "", fmt.Errorf("error getting device enabled state: %v", err)
	}

	if !resultOpenCheck.Value().(bool) {
		return "", fmt.Errorf("весы не подключены")
	}

	fmt.Println("Вызов получить вес...")
	// Вызов получить вес
	result, err := oleutil.CallMethod(scale, "ReadWeight")
	if err != nil {
		return "", fmt.Errorf("error calling ReadWeight: %v", err)
	}

	fmt.Println("Получение веса...")
	var weight *ole.VARIANT
	if result.Val == 0 {
		var err error
		fmt.Println("result.Val == 0")
		// Получение параметра веса
		weight, err = oleutil.GetProperty(scale, "Weight")
		fmt.Println("weight:", weight)
		if err != nil {
			return "", fmt.Errorf("error getting weight parameter: %v", err)
		}

		weightValue := fmt.Sprint(weight.Value())

		return weightValue, nil
	} else {
		fmt.Println("result.Val != 0")
		// Очистка параметров
		resultDescription, err := oleutil.GetProperty(scale, "ResultDescription")
		if err != nil {
			return "", fmt.Errorf("error getting weight, error getting result description: %v", err)
		}
		return "", fmt.Errorf("error getting weight, result description: %s", resultDescription.ToString())
	}
}
