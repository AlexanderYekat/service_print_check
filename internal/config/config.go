package config

import "flag"

type Settings struct {
	ClearLogs     bool
	Debug         int
	Com           int
	Cassir        string
	IpKKT         string
	PortKKT       int
	IpServKKT     string
	Emulation     bool
	AllowedOrigin string
}

var Current Settings

func Init() {
	flag.IntVar(&Current.Com, "com", 0, "ком порт кассы")
	flag.StringVar(&Current.Cassir, "cassir", "", "имя кассира")
	flag.StringVar(&Current.IpKKT, "ipkkt", "", "ip адрес ккт")
	flag.IntVar(&Current.PortKKT, "portipkkt", 0, "порт ip ккт")
	flag.StringVar(&Current.IpServKKT, "ipservkkt", "", "ip адрес сервера ккт")
	flag.StringVar(&Current.AllowedOrigin, "allowedOrigin", "", "разрешенный origin для WebSocket соединений")
	flag.Parse()
}
