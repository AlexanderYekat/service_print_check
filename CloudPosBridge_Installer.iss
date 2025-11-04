[Setup]
; Название вашего приложения, которое будет отображаться в Установке и Панели управления
AppName=CloudPosBridgePHP Service
; Версия вашего приложения
AppVersion=2025.11.04.06
; Имя файла установки, который будет создан
OutputBaseFilename=CloudPosBridgePHP_Setup
; Папка, куда по умолчанию будет установлено приложение
;DefaultDirName={localappdata}\CloudPosBridgePHP
DefaultDirName=c:\CloudPosBridgePHP
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

[Tasks]
Name: desktopicon; Description: "Создать ярлык на рабочем столе"; GroupDescription: "Дополнительные ярлыки:";
Name: programgroupicon; Description: "Создать ярлык в меню 'Пуск'"; GroupDescription: "Дополнительные ярлыки:"; Flags: unchecked
Name: register_sbrf_dll; Description: "Зарегистрировать библиотеку сбербанка (sbrf.dll из папки c:\sc552) для работы банковского терминала"; GroupDescription: "Регистрация DLL-библиотек:";
Name: install_fdu_driver; Description: "Установить драйвер вкесов (FDU_8_28_18_00_Full.EXE)"; GroupDescription: "Установка драйверов:";
Name: install_vcredist; Description: "Установить Microsoft Visual C++ Redistributable (автоматический выбор версии)"; GroupDescription: "Установка компонентов:";

[Files]
; Копируем все файлы из папки @myapp_dist/php в подпапку {app}\php
Source: "myapp_dist\php\*"; DestDir: "{app}\php"; Flags: recursesubdirs createallsubdirs ignoreversion
; Копируем nssm.exe из папки @myapp_dist/nssm в подпапку {app}\nssm
Source: "myapp_dist\nssm\nssm.exe"; DestDir: "{app}\nssm"; Flags: ignoreversion

; Копируем все файлы из папки @myapp_dist в подпапку drivers
Source: "myapp_dist\KKT10-10.10.7.0-windows32-setup.exe"; DestDir: "{app}\drivers"; Flags: ignoreversion
Source: "myapp_dist\KKT10-10.9.1.0-windows32-setup.exe"; DestDir: "{app}\drivers"; Flags: ignoreversion
Source: "myapp_dist\FDU_8_28_18_00_Full.EXE"; DestDir: "{app}\drivers"; Flags: ignoreversion
Source: "myapp_dist\VC_redist.x64.exe"; DestDir: "{app}\drivers"; Flags: ignoreversion
Source: "myapp_dist\VC_redist.x86.exe"; DestDir: "{app}\drivers"; Flags: ignoreversion

; Копируем все PHP файлы из корневой папки приложения в {app}\app
Source: "*.php"; DestDir: "{app}\app"; Flags: 
; Копируем README.md в {app}\app
Source: "README.md"; DestDir: "{app}\app"; Flags: 
; Упаковываем Composer-зависимости
Source: "vendor\*"; DestDir: "{app}\app\vendor"; Flags: recursesubdirs createallsubdirs ignoreversion
; Конфиг RoadRunner
Source: "rr.yaml"; DestDir: "{app}\app"; Flags: ignoreversion
; Бинарь RoadRunner (должен быть положен в myapp_dist\rr на машине сборки)
Source: "myapp_dist\rr\rr.exe"; DestDir: "{app}\rr"; Flags: ignoreversion
; Копируем файлы из папки templates в {app}\app\templates
Source: "templates\*"; DestDir: "{app}\app\templates"; Flags: recursesubdirs createallsubdirs
; Копируем файлы из папки settings_storage в {app}\app\settings_storage
Source: "settings_storage\*"; DestDir: "{app}\app\settings_storage"; Flags: recursesubdirs createallsubdirs
; Копируем файлы из папки tests в {app}\app\tests
Source: "tests\*"; DestDir: "{app}\app\tests"; Flags: recursesubdirs createallsubdirs
; Копируем файлы из папки resource в {app}\app\resource
Source: "resource\*"; DestDir: "{app}\app\resource"; Flags: recursesubdirs createallsubdirs
; Копируем файлы из папки bank в {app}\app\bank
Source: "bank\*"; DestDir: "{app}\app\bank"; Flags: recursesubdirs createallsubdirs
; Копируем файлы из папки scanner в {app}\app\scanner
Source: "scanner\*"; DestDir: "{app}\app\scanner"; Flags: recursesubdirs createallsubdirs
; Копируем все powershell скрипты из корневой папки приложения в {app}\app
Source: "*.ps1"; DestDir: "{app}\app"; Flags: 

[Dirs]
; Создаем необходимые директории, если они еще не существуют
Name: "{app}\app\settings"
Name: "{app}\app\logs"
Name: "{app}\drivers"
Name: "{app}\app\bank\temp"

[Icons]
; Ярлык в папке установки (всегда создается)
Name: "{app}\Настройки CloudPosBridgePHP.url"; Filename: "http://localhost:8000/"; Comment: "Открыть страницу настроек службы CloudPosBridgePHP"; IconFilename: "{app}\app\resource\icon.ico"

; Опциональный ярлык на рабочем столе
Name: "{autodesktop}\Настройки CloudPosBridgePHP.url"; Filename: "http://localhost:8000/"; Tasks: desktopicon; Comment: "Открыть страницу настроек службы CloudPosBridgePHP"; IconFilename: "{app}\app\resource\icon.ico"

; Опциональный ярлык в меню 'Пуск'
Name: "{group}\Настройки CloudPosBridgePHP.url"; Filename: "http://localhost:8000/"; Tasks: programgroupicon; Comment: "Открыть страницу настроек службы CloudPosBridgePHP"; IconFilename: "{app}\app\resource\icon.ico"

[Run]
; Установка службы Windows (RoadRunner) с помощью NSSM
Filename: "{app}\nssm\nssm.exe"; Parameters: "install CloudPosBridgeServicePHP ""{app}\rr\rr.exe"""; WorkingDir: "{app}\nssm"; StatusMsg: "Установка службы CloudPosBridgePHP Service..."; Flags: runhidden

; Регистрация DLL-библиотек
Filename: "{sys}\regsvr32.exe"; Parameters: "/s ""c:\sc552\sbrf.dll"""; Flags: runhidden; StatusMsg: "Регистрация sbrf.dll..."; Tasks: register_sbrf_dll

; Установка драйвера ККТ и драйвера весов
Filename: "{app}\drivers\KKT10-10.9.1.0-windows32-setup.exe"; Parameters: ""; Flags: waituntilterminated; StatusMsg: "Установка драйвера ККТ (32x битный) (АТОЛ) (старый)..."; Check: ShouldInstallOldKKTDriver
Filename: "{app}\drivers\KKT10-10.10.7.0-windows32-setup.exe"; Parameters: ""; Flags: waituntilterminated; StatusMsg: "Установка драйвера ККТ (32x битный) (новый)..."; Check: ShouldInstallNewKKTDriver
Filename: "{app}\drivers\FDU_8_28_18_00_Full.EXE"; Parameters: ""; Flags: waituntilterminated; StatusMsg: "Установка драйвера FDU..."; Tasks: install_fdu_driver

; Установка Microsoft Visual C++ Redistributable в зависимости от архитектуры системы
Filename: "{app}\drivers\VC_redist.x64.exe"; Parameters: "/quiet"; Flags: waituntilterminated; StatusMsg: "Установка Microsoft Visual C++ Redistributable (x64)..."; Tasks: install_vcredist; Check: IsWin64
Filename: "{app}\drivers\VC_redist.x86.exe"; Flags: waituntilterminated; StatusMsg: "Установка Microsoft Visual C++ Redistributable (x86)..."; Tasks: install_vcredist; Check: not IsWin64

; Параметры RoadRunner: использовать конфиг rr.yaml из папки приложения
Filename: "{app}\nssm\nssm.exe"; Parameters: "set CloudPosBridgeServicePHP AppParameters ""serve -c {app}\app\rr.yaml"""; WorkingDir: "{app}\nssm"; StatusMsg: "Настройка параметров RoadRunner..."; Flags: runhidden

; Установка отображаемого имени службы
Filename: "{app}\nssm\nssm.exe"; Parameters: "set CloudPosBridgeServicePHP DisplayName ""CloudPosBridgePHP Service"""; WorkingDir: "{app}\nssm"; StatusMsg: "Настройка службы CloudPosBridgePHP Service..."; Flags: runhidden

; Настройка перенаправления стандартного вывода и ошибок
Filename: "{app}\nssm\nssm.exe"; Parameters: "set CloudPosBridgeServicePHP AppStdout ""{app}\app\logs\nssm_stdout.log"""; WorkingDir: "{app}\nssm"; Flags: runhidden
Filename: "{app}\nssm\nssm.exe"; Parameters: "set CloudPosBridgeServicePHP AppStderr ""{app}\app\logs\nssm_stderr.log"""; WorkingDir: "{app}\nssm"; Flags: runhidden
; Включаем ротацию логов
Filename: "{app}\nssm\nssm.exe"; Parameters: "set CloudPosBridgeServicePHP AppRotateFiles 1"; WorkingDir: "{app}\nssm"; Flags: runhidden
; Ротация каждые 1 МБ
Filename: "{app}\nssm\nssm.exe"; Parameters: "set CloudPosBridgeServicePHP AppRotateBytes 1048576"; WorkingDir: "{app}\nssm"; Flags: runhidden
; Ротация ежедневно (86400 секунд = 24 часа)
Filename: "{app}\nssm\nssm.exe"; Parameters: "set CloudPosBridgeServicePHP AppRotateSeconds 86400"; WorkingDir: "{app}\nssm"; Flags: runhidden
; Включаем онлайн-ротацию
Filename: "{app}\nssm\nssm.exe"; Parameters: "set CloudPosBridgeServicePHP AppRotateOnline 1"; WorkingDir: "{app}\nssm"; Flags: runhidden
; Сохраняем 7 последних лог-файлов
Filename: "{app}\nssm\nssm.exe"; Parameters: "set CloudPosBridgeServicePHP AppRotateKeep 7"; WorkingDir: "{app}\nssm"; Flags: runhidden

; Установка описания службы
Filename: "{app}\nssm\nssm.exe"; Parameters: "set CloudPosBridgeServicePHP Description ""Служба для взаимодействия с ККТ, банковскими терминалами и весами через CloudPosBridgePHP."""; WorkingDir: "{app}\nssm"; StatusMsg: "Настройка службы CloudPosBridge PHP Service..."; Flags: runhidden

; Установка папки приложения для NSSM (очень важно для корректной работы путей PHP)
Filename: "{app}\nssm\nssm.exe"; Parameters: "set CloudPosBridgeServicePHP AppDirectory ""{app}\app"""; WorkingDir: "{app}\nssm"; StatusMsg: "Настройка директории службы..."; Flags: runhidden

; Запуск службы
Filename: "{app}\nssm\nssm.exe"; Parameters: "start CloudPosBridgeServicePHP"; WorkingDir: "{app}\nssm"; StatusMsg: "Запуск службы CloudPosBridgePHP Service..."; Flags: runhidden

; Создание задания планировщика для работы с банковским терминалом
Filename: "schtasks.exe"; \
Parameters: "/create /tn ""BankOperationTask"" /tr ""{app}\app\bank\mainbeznal.exe"" /sc ONCE /st 00:00 /f"; \
Flags: runhidden; \
StatusMsg: "Создание задания BankOperationTask для возврата денег..."

[UninstallRun]
; Остановка службы
Filename: "{app}\nssm\nssm.exe"; Parameters: "stop CloudPosBridgeServicePHP"; WorkingDir: "{app}\nssm"; Flags: runhidden waituntilterminated; RunOnceId: "stop_service"
; Удаление службы
Filename: "{app}\nssm\nssm.exe"; Parameters: "remove CloudPosBridgeServicePHP confirm"; WorkingDir: "{app}\nssm"; Flags: runhidden; RunOnceId: "remove_service"

[UninstallDelete]
Type: filesandordirs; Name: "{app}\app\settings"; Check: ShouldDeleteSettings
Type: filesandordirs; Name: "{app}"

[Messages]
WelcomeLabel2=Добро пожаловать в мастер установки CloudPosBridgePHP Service.%n%nПеред установкой, пожалуйста, убедитесь, что все необходимые драйверы для ККТ, банковского терминала и весов установлены и зарегистрированы на вашем компьютере.

[Code]
var
  KKTDriverPage: TInputOptionWizardPage;
  //DeleteSettingsCheck: TCheckBox;

function InitializeSetup(): Boolean;
begin
  Result := True;
end;

procedure InitializeWizard();
begin
  // Создаем новую страницу для выбора версии драйвера ККТ
  KKTDriverPage := CreateInputOptionPage(wpWelcome, 'Выбор драйвера ККТ', 'Пожалуйста, выберите версию драйвера ККТ для установки:',
    'Какой кассовый аппарат вы используете?', True, False);

  KKTDriverPage.Add('Не устанавливать драйвер ККТ');
  KKTDriverPage.Add('Кассовый аппарат старый (не обновлялся) (KKT10-10.9.1.0-windows32-setup.exe)');
  KKTDriverPage.Add('Кассовый аппарат новый (обновлялся) (KKT10-10.10.7.0-windows32-setup.exe)');
  
  // По умолчанию выбираем "не устанавливать" (индекс 0)
  KKTDriverPage.SelectedValueIndex := 2;
end;

var
  DeleteSettingsFlag: Boolean;

function InitializeUninstall(): Boolean;
begin
  Result := True;
  DeleteSettingsFlag := False; // По умолчанию не удаляем
end;

procedure CurUninstallStepChanged(CurUninstallStep: TUninstallStep);
begin
  if CurUninstallStep = usUninstall then
  begin
    // Показываем диалог перед началом удаления файлов
    if MsgBox('Удалить папку с настройками приложения?' + #13#10 + 
              'Если вы планируете переустановить приложение, рекомендуется сохранить настройки.', 
              mbConfirmation, MB_YESNO or MB_DEFBUTTON2) = IDYES then
    begin
      DeleteSettingsFlag := True;
    end;
  end;
end;

function ShouldDeleteSettings(): Boolean;
begin
  Result := DeleteSettingsFlag;
end;

function ShouldInstallOldKKTDriver(): Boolean;
begin
  Result := (KKTDriverPage.SelectedValueIndex = 1);
end;

function ShouldInstallNewKKTDriver(): Boolean;
begin
  Result := (KKTDriverPage.SelectedValueIndex = 2);
end;

procedure CurStepChanged(CurStep: TSetupStep);
var
  ResultCode: Integer;
begin
  if CurStep = ssInstall then
  begin
    // Остановка службы перед копированием файлов
    Exec(ExpandConstant('{app}\nssm\nssm.exe'), 'stop CloudPosBridgeServicePHP', '', SW_HIDE, ewWaitUntilTerminated, ResultCode);
  end;
  if CurStep = ssPostInstall then
  begin
    // Запуск службы после установки
    Exec(ExpandConstant('{app}\nssm\nssm.exe'), 'start CloudPosBridgeServicePHP', '', SW_HIDE, ewNoWait, ResultCode);
  end;
end;