package main

import (
	consttypes "service_print_check/consttypes"

	"github.com/stretchr/testify/mock"
)

type TTestMockPrinter struct {
	mock.Mock
}

func (m *TTestMockPrinter) PrintXReport(consttypes.IFptr10Interface) error {
	args := m.Called()
	return args.Error(0)
}

func (m *TTestMockPrinter) PrintSlip(consttypes.IFptr10Interface, string) error {
	args := m.Called()
	return args.Error(0)
}

func (m *TTestMockPrinter) PrintText(consttypes.IFptr10Interface, string) error {
	args := m.Called()
	return args.Error(0)
}

type TTestMockFptr struct {
	mock.Mock
}

func (m *TTestMockFptr) SetSingleSetting(param string, value string) {
	m.Called(param, value)
}

func (m *TTestMockFptr) Version() string {
	args := m.Called()
	return args.String(0)
}

func (m *TTestMockFptr) SetParam(param int32, value interface{}) {

	m.Called(param, value)
}

func (m *TTestMockFptr) GetParam(param int) string {
	args := m.Called(param)
	return args.String(0)
}

func (m *TTestMockFptr) ProcessJson() error {
	args := m.Called()
	return args.Error(0)
}

func (m *TTestMockFptr) Open() error {
	args := m.Called()

	return args.Error(0)
}

func (m *TTestMockFptr) IsOpened() bool {

	args := m.Called()
	return args.Bool(0)
}

func (m *TTestMockFptr) Destroy() {
	m.Called()

}

func (m *TTestMockFptr) SetSettings() {
	m.Called()
}

func (m *TTestMockFptr) ApplySingleSettings() error {
	args := m.Called()
	return args.Error(0)
}

func (m *TTestMockFptr) Connect() {
	m.Called()
}
func (m *TTestMockFptr) Close() error {
	args := m.Called()
	return args.Error(0)
}

func (m *TTestMockFptr) GetVersion() string {
	args := m.Called()
	return args.String(0)
}

func (m *TTestMockFptr) GetParamString(param int) string {
	args := m.Called()
	return args.String(0)
}

func (m *TTestMockFptr) ExecDriverCommand(command string) (string, error) {

	args := m.Called(command)
	return args.String(0), args.Error(1)
}
