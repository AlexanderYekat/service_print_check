program Scale_T;

uses
  Forms,
  Scale1C_TLB in 'UNITS\Scale1C_TLB.pas',
  fmuMain in 'FMU\fmuMain.pas' {fmMain},
  fmuDevice in 'FMU\fmuDevice.pas' {fmDevices},
  xScale in 'UNITS\xScale.pas',
  cuUtils in 'UNITS\cuUtils.pas',
  BDrvReg in 'UNITS\BDrvReg.pas';

{$R *.RES}

var
  pUnknown: IUnknown;
begin
  if CreateDriver(Class_Scale45, 'Scale1C.dll', pUnknown) then
  begin
    try
      Application.Initialize;
      Application.Title := 'Тест: драйвер электронных весов';
      Application.CreateForm(TfmMain, fmMain);
  Application.Run;
    finally
      pUnknown := nil;
    end;
  end;
end.
