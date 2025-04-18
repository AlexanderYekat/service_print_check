module scale_test

go 1.21.4

require service_print_check v0.0.0

require (
	github.com/go-ole/go-ole v1.3.0 // indirect
	golang.org/x/sys v0.16.0 // indirect
)

replace service_print_check => ../
