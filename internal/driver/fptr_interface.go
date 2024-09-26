package driver

import fptr10 "service_print_check/internal/fptr"

type IFptr10Interface interface {
	//NewSafe() (IFptr10Interface, error)
	Destroy()
	Close() error
	Open() error
	IsOpened() bool
	ApplySingleSettings() error
	SetSingleSetting(name string, value string)
	ProcessJson() error
	GetParamString(name int) string
	SetParam(int32, interface{})
	Version() string
}

const (
	LIBFPTR_SETTING_MODEL              = fptr10.LIBFPTR_SETTING_MODEL
	LIBFPTR_SETTING_PORT               = fptr10.LIBFPTR_SETTING_PORT
	LIBFPTR_SETTING_BAUDRATE           = fptr10.LIBFPTR_SETTING_BAUDRATE
	LIBFPTR_SETTING_BITS               = fptr10.LIBFPTR_SETTING_BITS
	LIBFPTR_SETTING_PARITY             = fptr10.LIBFPTR_SETTING_PARITY
	LIBFPTR_SETTING_STOPBITS           = fptr10.LIBFPTR_SETTING_STOPBITS
	LIBFPTR_SETTING_IPADDRESS          = fptr10.LIBFPTR_SETTING_IPADDRESS
	LIBFPTR_SETTING_IPPORT             = fptr10.LIBFPTR_SETTING_IPPORT
	LIBFPTR_SETTING_MACADDRESS         = fptr10.LIBFPTR_SETTING_MACADDRESS
	LIBFPTR_SETTING_COM_FILE           = fptr10.LIBFPTR_SETTING_COM_FILE
	LIBFPTR_SETTING_REMOTE_SERVER_ADDR = fptr10.LIBFPTR_SETTING_REMOTE_SERVER_ADDR
	LIBFPTR_MODEL_ATOL_AUTO            = fptr10.LIBFPTR_MODEL_ATOL_AUTO
	LIBFPTR_PORT_TCPIP                 = fptr10.LIBFPTR_PORT_TCPIP
	LIBFPTR_PORT_USB                   = fptr10.LIBFPTR_PORT_USB
	LIBFPTR_PORT_COM                   = fptr10.LIBFPTR_PORT_COM
	LIBFPTR_PORT_BR_115200             = fptr10.LIBFPTR_PORT_BR_115200
	LIBFPTR_PARAM_JSON_DATA            = fptr10.LIBFPTR_PARAM_JSON_DATA
)
