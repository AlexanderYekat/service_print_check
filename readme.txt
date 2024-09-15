# Overview of Project service_print_check:
---
## Technologies: 

| Name                             | Version  |
| -------------------------------- | -------- |
| Go                               | 1.20.4   |
| github.com/gorilla/websocket     | 1.5.1    |
| github.com/rs/cors               | 1.9.0    |

---
## Project details:
---
### Detailed project folders description:

```
C:\Users\Enduro\Documents\latypova\kassa\service_print_check
├── consttypes - contains constant types and definitions used throughout the project
├── fptr - provides an interface to interact with fiscal printers using the Fptr10 library
├── packetlog - handles logging operations, including initialization and writing logs to files
└── resource - stores resources such as icons and manifests used for building the application
```

---
### Full Business logic:
- The project is a service designed to print checks from JSON data received via a WebSocket connection.
- It utilizes the Fptr10 library to communicate with Atol fiscal printers.
- The service supports printing checks, closing shifts, and printing X-reports.
- It handles WebSocket connections, parses JSON data, and interacts with the fiscal printer driver to execute commands.
- The service includes logging functionality to record program actions and errors.

---
### Dependency management:
- The project uses Go modules for dependency management.
- Dependencies are listed in the `go.mod` file.
- The `go get` command is used to download and install dependencies.

---
### Notes:
- The project is configured to run on port 8081.
- It allows WebSocket connections from any origin.
- The service can be run in emulation mode, which simulates the interaction with the fiscal printer.
- The level of logging can be adjusted using the `-debug` flag.

---
## All endpoints/All events the project exposes and listens:

### WebSockets
- ws://localhost:8081/ws - Bi-directional communication channel for receiving print requests and sending responses.
    - printCheck - Request to print a check based on the provided JSON data.
    - closeShift - Request to close the current shift with the specified cashier name.
    - xReport - Request to print an X-report.

---
## All downstream services

- Atol Fiscal Printer - The service relies on an Atol fiscal printer to physically print checks and reports.

---
## All upstream services

- Any system capable of sending WebSocket requests with JSON data - This could be a web application, a point-of-sale system, or any other system that needs to print checks.

---
## Testing
- Dependencies: No specific testing dependencies are mentioned in the provided files.
- Summary of Found Test Flows: No explicit test flows are defined in the code.
- Fixtures Storages: No fixture storage mechanisms are apparent.
- Useful to know: Testing would likely involve simulating WebSocket requests and verifying the interaction with the Fptr10 library, potentially using mocks or stubs.

---
## Deployment
- Summary: The project can be built into an executable using the `go build` command. The executable can then be deployed to a server and run as a background process.

---
## Setting Up the Development Environment

1. **Install Go:** Download and install the Go programming language from the official website: https://golang.org/
2. **Set up Go Modules:** Ensure Go modules are enabled by setting the `GO111MODULE` environment variable to `on`.
3. **Install Dependencies:** Navigate to the project directory and run `go get` to download and install the required dependencies.

---
## Running the Project in the Development Environment

### Execution Instructions

- **Local Mode**:
```shell
go run jsontokkt.go
```
This command will compile and run the project in local mode, listening for WebSocket connections on port 8081.

---
## Sequence diagrams for each API/event with all downstream dependencies and data details:

- Event: Print Check Request
```sequence
Client->service_print_check: printCheck({"tableData": [...], "employee": "...", "master": "..."})
service_print_check->Fptr10 Library: connectWithKassa(comport, ipaddresskkt, portkktatol, ipaddressservrkkt)
Fptr10 Library->Atol Fiscal Printer: Connect
service_print_check->Fptr10 Library: checkOpenShift(openShiftIfClose, kassir)
Fptr10 Library->Atol Fiscal Printer: Get Status
service_print_check->Fptr10 Library: sendComandeAndGetAnswerFromKKT(checkJSON)
Fptr10 Library->Atol Fiscal Printer: Print Check
service_print_check->Client: printCheckResponse("Чек успешно напечатан")
```
- Event: Close Shift Request
```sequence
Client->service_print_check: closeShift({"cashier": "..."})
service_print_check->Fptr10 Library: connectWithKassa(comport, ipaddresskkt, portkktatol, ipaddressservrkkt)
Fptr10 Library->Atol Fiscal Printer: Connect
service_print_check->Fptr10 Library: sendComandeAndGetAnswerFromKKT(closeShiftJSON)
Fptr10 Library->Atol Fiscal Printer: Close Shift
service_print_check->Client: printCheckResponse("Смена успешно закрыта")
```
- Event: Print X-Report Request
```sequence
Client->service_print_check: xReport()
service_print_check->Fptr10 Library: connectWithKassa(comport, ipaddresskkt, portkktatol, ipaddressservrkkt)
Fptr10 Library->Atol Fiscal Printer: Connect
service_print_check->Fptr10 Library: sendComandeAndGetAnswerFromKKT(xReportJSON)
Fptr10 Library->Atol Fiscal Printer: Print X-Report
service_print_check->Client: printCheckResponse("X-отчет успешно напечатан")
```

---
