package kktutils

import (
	"encoding/json"
	"service_print_check/models"
	"strconv"
	"strings"
)

// FormatCheckJSON форматирует данные чека в JSON для отправки на ККТ
func FormatCheckJSON(checkData models.CheckData) string {
	// Здесь формируем JSON для печати чека в соответствии с форматом, ожидаемым ККТ
	// Пример:
	//none - налогом не облагается
	//vat0 - НДС 0%
	//vat10 - НДС 10%
	//vat110 - НДС 10/110
	//vat20 - НДС 20%
	//vat120 - НДС 20/120
	//vat5 - НДС 5%
	//vat105 - НДС 5/105
	//vat7 - НДС 7%
	//vat107 - НДС 7/107	+
	checkItems := make([]map[string]interface{}, len(checkData.TableData))
	for i, item := range checkData.TableData {
		var taxType string
		if item.TaxNDS == "" {
			taxType = "none" // Если TaxNDS пустой, устанавливаем "none"
		} else if !strings.HasPrefix(item.TaxNDS, "vat") {
			taxType = "vat" + item.TaxNDS // Добавляем префикс "vat", если его нет
		} else {
			taxType = item.TaxNDS // Возвращаем TaxNDS, если он уже с префиксом
		}
		quantity, _ := strconv.ParseFloat(item.Quantity, 64)
		price, _ := strconv.ParseFloat(item.Price, 64)
		checkItems[i] = map[string]interface{}{
			"type":     "position",
			"name":     item.Name,
			"price":    price,
			"quantity": quantity,
			"amount":   price * quantity,
			"tax": map[string]interface{}{
				"type": taxType, //
			},
		}
	}

	// Формируем массив оплат
	payments := make([]map[string]interface{}, 0)
	totalAmount := 0.0

	// Вычисляем общую сумму чека
	for _, item := range checkData.TableData {
		quantity, _ := strconv.ParseFloat(item.Quantity, 64)
		price, _ := strconv.ParseFloat(item.Price, 64)
		totalAmount += quantity * price
	}

	if len(checkData.Payments) == 0 {
		// Если платежи не переданы, используем оплату наличными по умолчанию
		payments = append(payments, map[string]interface{}{
			"type": "cash",
			"sum":  totalAmount,
		})
	} else {
		for _, payment := range checkData.Payments {
			payments = append(payments, map[string]interface{}{
				"type": payment.Type,
				"sum":  payment.Amount,
			})
		}
	}

	checkType := "sell"
	if checkData.Type != "" {
		checkType = checkData.Type
	}

	checkJSON := map[string]interface{}{
		"type": checkType,
		"operator": map[string]string{
			"name": checkData.Cashier,
		},
		"items":    checkItems,
		"payments": payments,
	}

	// Добавляем поле taxationType только если оно не пустое
	if checkData.TaxationType != "" {
		checkJSON["taxationType"] = checkData.TaxationType
	}

	jsonBytes, _ := json.Marshal(checkJSON)
	return string(jsonBytes)
}
