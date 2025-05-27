module scale_test

go 1.23.0

toolchain go1.23.1

require service_print_check v0.0.0

require (
	github.com/go-ole/go-ole v1.3.0 // indirect
	golang.org/x/sys v0.32.0 // indirect
)

replace service_print_check => ../
