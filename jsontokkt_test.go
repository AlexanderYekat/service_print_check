package main

import (
	"encoding/json"
	"errors"
	"fmt"
	"net/http"
	"net/http/httptest"
	"testing"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/mock"
)

type TTestMockPrinter struct {
	mock.Mock
}

func (m *TTestMockPrinter) PrintXReport(IFptr10Interface) error {
	args := m.Called()
	return args.Error(0)
}

type TTestMockFptr struct {
	mock.Mock
}

func TestHandleXReport(t *testing.T) {

	tests := []struct {
		name           string
		method         string
		mockError      error
		expectedStatus int
		expectedBody   map[string]string
	}{
		{
			name:           "Successful X-Report",
			method:         http.MethodPost,
			mockError:      nil,
			expectedStatus: http.StatusOK,
			expectedBody:   map[string]string{"status": "success", "message": "X-отчет успешно напечатан"},
		},
		{
			name:           "Method Not Allowed",
			method:         http.MethodGet,
			expectedStatus: http.StatusMethodNotAllowed,
		},
		{
			name:           "Printer Error",
			method:         http.MethodPost,
			mockError:      errors.New("printer error"),
			expectedStatus: http.StatusInternalServerError,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			mockPrinter := new(TTestMockPrinter)
			fmt.Println("--------------начало---------------------")
			fmt.Println("tt.name", tt.name)

			fmt.Println("tt.method", tt.method)

			fmt.Println("tt.mockError", tt.mockError)
			if tt.method == http.MethodPost {
				mockPrinter.On("PrintXReport").Return(tt.mockError).Run(func(args mock.Arguments) {
					fmt.Printf("PrintXReport вызван, возвращает ошибку: %v\n", tt.mockError)
				})
			}
			//printXReportFunc := mockPrinter.On("printXReport").Return(tt.mockError)

			// Восстановление оригинальной функции после теста

			req, err := http.NewRequest(tt.method, "/api/x-report", nil)

			assert.NoError(t, err)

			rr := httptest.NewRecorder()
			handler := http.HandlerFunc(handleXReport(mockPrinter))

			handler.ServeHTTP(rr, req)
			fmt.Println("rr.Code", rr.Code)
			//fmt.Println("rr.Body.Bytes()", rr.Body.Bytes())
			fmt.Println("tt.expectedStatus", tt.expectedStatus)
			fmt.Println("tt.expectedBody", tt.expectedBody)

			assert.Equal(t, tt.expectedStatus, rr.Code)

			if tt.expectedBody != nil {
				var response map[string]string
				err = json.Unmarshal(rr.Body.Bytes(), &response)
				assert.NoError(t, err)
				assert.Equal(t, tt.expectedBody, response)
			}

			mockPrinter.AssertExpectations(t)
			fmt.Println("--------------конец---------------------")
		})
	}
}
