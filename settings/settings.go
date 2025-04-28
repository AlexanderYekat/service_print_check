package settings

import (
	"encoding/json"
	"fmt"
	"io/ioutil"
	"os"
	"service_print_check/consttypes"
)

type TSettings struct {
	ClearLogs     bool   `json:"clearlogs"`
	Debug         int    `json:"debug"`
	ComKKT        int    `json:"comkkt"`
	Cassir        string `json:"cassir"`
	IpKKT         string `json:"ipkkt"`
	PortKKT       int    `json:"portipkkt"`
	IpServKKT     string `json:"ipservkkt"`
	Emulation     bool   `json:"emul"`
	AllowedOrigin string `json:"allowedOrigin"`
}

var FullFileNameSettings = consttypes.SETTINGSDIR + consttypes.FILESETTINGS

func InitializationsSettings() (TSettings, error) {
	if foundedSettingsDir, _ := consttypes.DoesFileExist(consttypes.SETTINGSDIR); !foundedSettingsDir {
		if err := os.Mkdir(consttypes.SETTINGSDIR, 0755); err != nil {
			return TSettings{}, err
		}
	}
	defaultSettings := TSettings{
		ClearLogs:     true,
		Debug:         3,
		ComKKT:        0,
		Cassir:        "Кассир",
		IpKKT:         "",
		PortKKT:       0,
		IpServKKT:     "",
		Emulation:     false,
		AllowedOrigin: "",
	}
	// Если файла настроек нет — создаём его с дефолтными значениями
	if _, err := os.Stat(FullFileNameSettings); os.IsNotExist(err) {
		fmt.Println("файл настроек ", FullFileNameSettings, " не найден, создаём файл настроек с дефолтными значениями")
		if err := saveSettings(defaultSettings, FullFileNameSettings); err != nil {
			return TSettings{}, err
		}
	}
	fmt.Println("файл настроек ", FullFileNameSettings, " найден, загружаем настройки")
	currentSettings, err := LoadSettings()
	return currentSettings, err

}

func SaveSettings(settings TSettings) error {
	err := saveSettings(settings, FullFileNameSettings)
	return err
}

// SaveSettings сохраняет настройки в указанный файл
func saveSettings(settings TSettings, filename string) error {
	file, err := os.Create(filename)
	if err != nil {
		return err
	}
	defer file.Close()
	encoder := json.NewEncoder(file)
	encoder.SetIndent("", "  ")
	return encoder.Encode(settings)
}

// LoadSettings загружает настройки из указанного файла
func LoadSettings() (TSettings, error) {
	var settings TSettings
	data, err := ioutil.ReadFile(FullFileNameSettings)
	if err != nil {
		return settings, err
	}
	err = json.Unmarshal(data, &settings)
	return settings, err
}
