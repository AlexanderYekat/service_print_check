[Setup]
; Название вашего приложения, которое будет отображаться в Установке и Панели управления
AppName=CloudPosBridge Service
; Версия вашего приложения
AppVersion=1.0.0
; Имя файла установки, который будет создан
OutputBaseFilename=CloudPosBridge_Setup
; Папка, куда по умолчанию будет установлено приложение
DefaultDirName={autopf}\CloudPosBridge
; Разрешить установку только для текущего пользователя или для всех пользователей
PrivilegesRequired=admin
; Разрешить установку на 32-битных и 64-битных системах
ArchitecturesAllowed=x86 x64compatible
; Устанавливать в 32-битную папку Program Files (x86) на 64-битных системах
ArchitecturesInstallIn64BitMode=x86
; Тип компиляции для 64-битных систем
SolidCompression=yes
;LZMACompressionLevel=max
Compression=lzma

[Files]
; Копируем все файлы из папки @myapp_dist/php в подпапку {app}\php
Source: "myapp_dist\php\*"; DestDir: "{app}\php"; Flags: recursesubdirs createallsubdirs
; Копируем все файлы из папки @myapp_dist/app в подпапку {app}\app
Source: "myapp_dist\app\*"; DestDir: "{app}\app"; Flags: recursesubdirs createallsubdirs
; Копируем nssm.exe из папки @myapp_dist/nssm в подпапку {app}\nssm
Source: "myapp_dist\nssm\nssm.exe"; DestDir: "{app}\nssm"; Flags: ignoreversion

[Dirs]
; Создаем необходимые директории, если они еще не существуют
Name: "{app}\app\settings"
Name: "{app}\app\logs"

[Icons]
; Создаем ярлык на рабочем столе для настроек (необязательно, но удобно)
Name: "{group}\Настройки CloudPosBridge"; Filename: "{app}\php\php.exe"; Parameters: "-S localhost:3000 -t ""{app}\app\templates"""; WorkingDir: "{app}\app"; Comment: "Запустить тестовый веб-сервер для настроек"
Name: "{autodesktop}\Настройки CloudPosBridge"; Filename: "{app}\php\php.exe"; Parameters: "-S localhost:3000 -t ""{app}\app\templates"""; WorkingDir: "{app}\app"; Comment: "Запустить тестовый веб-сервер для настроек"

[Run]
; Установка службы Windows с помощью NSSM
Filename: "{app}\nssm\nssm.exe"; Parameters: "install CloudPosBridgeService ""{app}\php\php.exe"""; WorkingDir: "{app}\nssm"; StatusMsg: "Установка службы CloudPosBridge Service..."; Flags: runhidden

; Установка параметров приложения для PHP (тестовый скрипт)
;Filename: "{app}\nssm\nssm.exe"; Parameters: "set CloudPosBridgeService AppArgs ""-f"" ""{app}\app\test_service.php"""; WorkingDir: "{app}\nssm"; StatusMsg: "Настройка параметров PHP скрипта..."; Flags: runhidden
Filename: "{app}\nssm\nssm.exe"; Parameters: "set CloudPosBridgeService AppArgs ""-f ""{app}\app\test_service.php"""""; WorkingDir: "{app}\nssm"; StatusMsg: "Настройка параметров PHP скрипта..."; Flags: runhidden

; Установка отображаемого имени службы
Filename: "{app}\nssm\nssm.exe"; Parameters: "set CloudPosBridgeService DisplayName ""CloudPosBridge Service"""; WorkingDir: "{app}\nssm"; StatusMsg: "Настройка службы CloudPosBridge Service..."; Flags: runhidden

; Настройка перенаправления стандартного вывода и ошибок
Filename: "{app}\nssm\nssm.exe"; Parameters: "set CloudPosBridgeService AppStdout ""{app}\app\logs\nssm_stdout.log"""; WorkingDir: "{app}\nssm"; Flags: runhidden
Filename: "{app}\nssm\nssm.exe"; Parameters: "set CloudPosBridgeService AppStderr ""{app}\app\logs\nssm_stderr.log"""; WorkingDir: "{app}\nssm"; Flags: runhidden
; Включаем ротацию логов
Filename: "{app}\nssm\nssm.exe"; Parameters: "set CloudPosBridgeService AppRotateFiles 1"; WorkingDir: "{app}\nssm"; Flags: runhidden
; Ротация каждые 1 МБ
Filename: "{app}\nssm\nssm.exe"; Parameters: "set CloudPosBridgeService AppRotateBytes 1048576"; WorkingDir: "{app}\nssm"; Flags: runhidden
; Ротация ежедневно (86400 секунд = 24 часа)
Filename: "{app}\nssm\nssm.exe"; Parameters: "set CloudPosBridgeService AppRotateSeconds 86400"; WorkingDir: "{app}\nssm"; Flags: runhidden

; Установка описания службы
Filename: "{app}\nssm\nssm.exe"; Parameters: "set CloudPosBridgeService Description ""Служба для взаимодействия с ККТ, банковскими терминалами и весами через CloudPosBridge."""; WorkingDir: "{app}\nssm"; StatusMsg: "Настройка службы CloudPosBridge Service..."; Flags: runhidden

; Установка папки приложения для NSSM (очень важно для корректной работы путей PHP)
Filename: "{app}\nssm\nssm.exe"; Parameters: "set CloudPosBridgeService AppDirectory ""{app}\app"""; WorkingDir: "{app}\nssm"; StatusMsg: "Настройка директории службы..."; Flags: runhidden

; Запуск службы
Filename: "{app}\nssm\nssm.exe"; Parameters: "start CloudPosBridgeService"; WorkingDir: "{app}\nssm"; StatusMsg: "Запуск службы CloudPosBridge Service..."; Flags: runhidden

[UninstallRun]
; Остановка службы
Filename: "{app}\nssm\nssm.exe"; Parameters: "stop CloudPosBridgeService"; WorkingDir: "{app}\nssm"; Flags: runhidden waituntilterminated; RunOnceId: "stop_service"
; Удаление службы
Filename: "{app}\nssm\nssm.exe"; Parameters: "remove CloudPosBridgeService confirm"; WorkingDir: "{app}\nssm"; Flags: runhidden; RunOnceId: "remove_service"

[Messages]
WelcomeLabel2=Добро пожаловать в мастер установки CloudPosBridge Service.%n%nПеред установкой, пожалуйста, убедитесь, что все необходимые драйверы для ККТ, банковского терминала и весов установлены и зарегистрированы на вашем компьютере.

[Code]
function InitializeSetup(): Boolean;
begin
  Result := True;
  MsgBox('Перед установкой, пожалуйста, убедитесь, что все необходимые драйверы (например, для COM-объектов SBRFSRV.Server, AddIn.Scale8) установлены и зарегистрированы на вашем компьютере.', mbInformation, MB_OK);
end;
