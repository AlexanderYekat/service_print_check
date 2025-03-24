package kktutils

import (
	"encoding/json"
	"fmt"
	"strings"
)

// FormatSlipToJSON преобразует многострочный текст в JSON для нефискального чека
func FormatSlipToJSON(slip string, alignment string) (string, error) {
	// Если выравнивание не указано, используем "left" по умолчанию
	if alignment == "" {
		alignment = "left"
	}

	// Формируем JSON для печати слипа
	lines := strings.Split(slip, "\n")
	items := make([]map[string]string, 0)

	for _, line := range lines {
		if strings.TrimSpace(line) != "" {
			items = append(items, map[string]string{
				"type":      "text",
				"text":      line,
				"alignment": alignment,
			})
		}
	}

	slipData := map[string]interface{}{
		"type":  "nonFiscal",
		"items": items,
	}

	slipJSON, err := json.Marshal(slipData)
	if err != nil {
		return "", fmt.Errorf("ошибка формирования JSON для печати слипа: %v", err)
	}

	return string(slipJSON), nil
}
