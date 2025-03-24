; Скрипт установки для CloudPosBridge
; Создан с помощью Inno Setup

[Setup]
AppId={{CLOUDPOS_BRIDGE_SERVICE}}
AppName=CloudPosBridge
AppVersion=1.0
AppPublisher=CloudPOS
DefaultDirName={pf32}\CloudPosBridge
DefaultGroupName=CloudPosBridge
DisableProgramGroupPage=yes
OutputDir=.
OutputBaseFilename=CloudPosBridge_Setup
Compression=lzma
SolidCompression=yes
SetupIconFile=resource\icon.ico
UninstallDisplayIcon={app}\service_CloudPosBridge.exe

[Files]
Source: "service_print_check.exe"; DestDir: "{app}"; DestName: "service_CloudPosBridge.exe"; Flags: ignoreversion

[Run]
; Установка и запуск службы
Filename: "sc.exe"; Parameters: "create CloudPosBridge binPath= ""{app}\service_CloudPosBridge.exe"" DisplayName= ""CloudPosBridge"" start= auto"; Flags: runhidden
Filename: "sc.exe"; Parameters: "description CloudPosBridge ""Служба печати чеков CloudPosBridge"""; Flags: runhidden
Filename: "sc.exe"; Parameters: "start CloudPosBridge"; Flags: runhidden

[UninstallRun]
; Остановка и удаление службы при деинсталляции
Filename: "sc.exe"; Parameters: "stop CloudPosBridge"; Flags: runhidden
Filename: "sc.exe"; Parameters: "delete CloudPosBridge"; Flags: runhidden
