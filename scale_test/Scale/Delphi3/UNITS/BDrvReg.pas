unit BDrvReg;

interface

uses
  Windows, SysUtils, Forms, Dialogs, ComObj, ActiveX;

resourcestring
  STR_REG_DLL = 'Необходимо зарегистрировать драйвер. '#13+
    'Укажите местоположение файла драйвера %s';

  STR_REG_LS =
    'Невозможно создать объект сервера оборудования %s.'#13 +
    'Возможные причины:'#13 +
    'сервер оборудования не зарегистрирован'#13 +
    'или компьютер, на котором он находится, недоступен.'#13#13 +
    '"Да"'#9#9'зарегистрировать сервер оборудования'#13 +
    '"Нет"'#9#9'продолжить выполнение программы без регистрации'#13 +
    '"Отмена"'#9'прервать выполнение программы';

type
  TRegisterResult = (
    rrNo,            // Пользователь не захотел регистрировать драйвер
    rrYes,           // Пользователь зарегистрировал драйвер
    rrCancel);       // Пользователь нажал кнопку отмена

function RegisterDriver(const FileName: String): TRegisterResult;
function RegisterServer(const FileName: String): TRegisterResult;
function RegisterExeServer(const FileName: string): Boolean;

function CreateDriver(
  const GUID: TGUID;
  const FileName: string;
  out pUnknown: IUnknown): Boolean;

function CreateDriver2(
  const GUID: TGUID;
  const DriverFileName, ServerFileName: string;
  out pUnknown: IUnknown): Boolean;

implementation

function CreateDriver(const GUID: TGUID; const FileName: string;
  out pUnknown: IUnknown): Boolean;
var
  CreateResult: HResult;
  RegisterResult: TRegisterResult;
begin
  repeat
    CreateResult := CoCreateInstance(GUID, nil, CLSCTX_INPROC_SERVER,
      IUnknown, pUnknown);
    Result := Succeeded(CreateResult);
    if Result then Break else
    begin
      if CreateResult = REGDB_E_CLASSNOTREG then
      begin
        RegisterResult := RegisterDriver(FileName);
        if RegisterResult in [rrNo, rrCancel] then Break;
      end else
        OleCheck(CreateResult);
    end;
  until False;
end;

function CreateDriver2(const GUID: TGUID;
  const DriverFileName, ServerFileName: string;
  out pUnknown: IUnknown): Boolean;
var
  Driver: OleVariant;
  CreateResult: HResult;
  RegisterResult: TRegisterResult;
begin
  repeat
    CreateResult := CoCreateInstance(GUID, nil, CLSCTX_INPROC_SERVER,
      IUnknown, pUnknown);
    Result := Succeeded(CreateResult);
    if Result then
    begin
      Driver := pUnknown as IDispatch;
      if Driver.ResultCode = -13 then
      begin
        case RegisterServer(ServerFileName) of
          rrNo: Break;
          rrCancel: begin Result := False; Break; end;
        end;
      end else Break;
    end else
    begin
      if CreateResult = REGDB_E_CLASSNOTREG then
      begin
        RegisterResult := RegisterDriver(DriverFileName);
        if RegisterResult in [rrNo, rrCancel] then Break;
      end else
        OleCheck(CreateResult);
    end;
  until False;
end;

function RegisterDriver(const FileName: String): TRegisterResult;
var
  OpenDialog: TOpenDialog;
begin
  with Application do
  if MessageBox(
     PChar(Format(STR_REG_DLL, [FileName])),
     PChar(Title),
     mb_IconExclamation or mb_OKCancel) = IdCancel then
  begin
    Result := rrCancel;
    Exit;
  end;
  OpenDialog := TOpenDialog.Create(nil);
  try
    OpenDialog.FileName := FileName;
    OpenDialog.Filter := 'Драйвер (*.dll)|*.dll|'+
      'Компонент OCX (*.OCX)|*.OCX|Все файлы (*.*)|*.*';
    OpenDialog.DefaultExt := 'dll';
    OpenDialog.Options := [ofHideReadOnly, ofPathMustExist, ofFileMustExist];
    if OpenDialog.Execute then
    begin
      Result := rrYes;
    end
    else
      Result := rrCancel;
    if Result <> rrCancel then
    begin
      try
        RegisterComServer(OpenDialog.FileName);
        Result := rrYes
      except
        Result := rrNo
      end;
    end;
  finally
    OpenDialog.Free;
  end;
end;

function RegisterServer(const FileName: String): TRegisterResult;
var
  OpenDialog: TOpenDialog;
begin
  case Application.MessageBox(
    PChar(Format(STR_REG_LS, [FileName])),
    PChar(Application.Title),
    mb_IconExclamation or mb_YesNoCancel) of
    ID_Cancel:
    begin
      Result := rrCancel;
      Exit;
    end;
    ID_No:
    begin
      Result := rrNo;
      Exit;
    end;
    ID_Yes:
    begin
      OpenDialog := TOpenDialog.Create(nil);
      try
        OpenDialog.FileName := FileName;
        OpenDialog.Filter := 'Сервер оборудования (*.exe)|*.exe|' +
          'Все файлы (*.*)|*.*';
        OpenDialog.DefaultExt := 'exe';
        OpenDialog.Options := [ofHideReadOnly, ofPathMustExist, ofFileMustExist];
        if not OpenDialog.Execute then
        begin
          Result := rrCancel;
          Exit;
        end;
        if RegisterExeServer(OpenDialog.FileName) then
          Result := rrYes
        else
          Result := rrNo
      finally
        OpenDialog.Free;
      end;
    end;
  else
    Result := rrCancel;
  end;
end;

function RegisterExeServer(const FileName: string): Boolean;
var
  StartupInfo: TStartupInfo;
  ProcessInfo: TProcessInformation;
  Directory: String;
  Command: String;
begin
  Result := False;
  Directory := ExtractFilePath(FileName);
  Command := ExtractFileName(FileName) + ' /RegServer';

  FillChar(StartupInfo, SizeOf(StartupInfo) , 0 );
  with StartupInfo do
  begin
    cb := SizeOf(StartupInfo);
    dwFlags := STARTF_USESHOWWINDOW;
    wShowWindow := SW_HIDE;
  end;
  if CreateProcess(
       nil,                         // lpApplicationName
       PChar(Command),              // lpCommandLine
       nil,                         // lpProcessAttributes
       nil,                         // lpThreadAttributes
       False,                       // bInheritHandles
       NORMAL_PRIORITY_CLASS,       // dwCreationFlags
       nil,                         // pEnvironment
       PChar(Directory),            // lpCurrentDirectory
       StartupInfo,                 // lpStartupInfo
       ProcessInfo)                 // lpProcessInformation
  then
  begin
    CloseHandle(ProcessInfo.hThread);
    WaitForSingleObject(ProcessInfo.hProcess, INFINITE);
    CloseHandle(ProcessInfo.hProcess);
    Result := True;
  end;
end;

end.
