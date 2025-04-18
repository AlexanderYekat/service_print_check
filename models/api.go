package models

// WSMessage представляет структуру входящего веб-сокет сообщения
type WSMessage struct {
	Command string         `json:"command"`
	Data    map[string]any `json:"data,omitempty"`
}

// WSResponse представляет структуру исходящего веб-сокет сообщения
type WSResponse struct {
	Type    string      `json:"type"`
	Message string      `json:"message"`
	Data    interface{} `json:"data,omitempty"`
	ID      string      `json:"id,omitempty"`   // Идентификатор сообщения (совпадает с ID запроса)
	Time    int64       `json:"time,omitempty"` // Время отправки ответа
}

// CheckItem представляет элемент чека
type CheckItem struct {
	Name     string `json:"name"`
	Quantity string `json:"quantity"`
	Price    string `json:"price"`
	TaxNDS   string `json:"taxNDS,omitempty"`
}

// Payment представляет информацию об оплате
type Payment struct {
	Type   string  `json:"type"`
	Amount float64 `json:"amount"`
}

// CheckData представляет данные чека
type CheckData struct {
	TaxationType string      `json:"taxationType",omitempty`
	Type         string      `json:"type"`
	Cashier      string      `json:"cashier"`
	TableData    []CheckItem `json:"tableData"`
	Payments     []Payment   `json:"payments"`
}

// Settings представляет настройки приложения
type Settings struct {
	ClearLogs     bool   `json:"clearlogs"`
	Debug         int    `json:"debug"`
	Com           int    `json:"com"`
	Cassir        string `json:"cassir"`
	IpKKT         string `json:"ipkkt"`
	PortKKT       int    `json:"portipkkt"`
	IpServKKT     string `json:"ipservkkt"`
	Emulation     bool   `json:"emul"`
	AllowedOrigin string `json:"allowedOrigin"`
}
