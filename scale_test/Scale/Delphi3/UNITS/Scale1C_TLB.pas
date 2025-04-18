unit Scale1C_TLB;

{ This file contains pascal declarations imported from a type library.
  This file will be written during each import or refresh of the type
  library editor.  Changes to this file will be discarded during the
  refresh process. }

{ АТОЛ: Драйвер электронных весов }
{ Version 5.2 }

interface

uses Windows, ActiveX, Classes, Graphics, OleCtrls, StdVCL;

const
  LIBID_Scale1C: TGUID = '{DCD43EE9-B38D-11D3-9DE1-0000E8DBEDCE}';

const

{ Component class GUIDs }
  //Class_Scale45: TGUID = '{DCD43EE6-B38D-11D3-9DE1-0000E8DBEDCE}';
  Class_Scale45: TGUID = '{CAF4DC44-2819-4CA5-991C-FDFBB66E0A92}';
  //{CAF4DC44-2819-4CA5-991C-FDFBB66E0A92}

type

{ Forward declarations: Interfaces }
  IScale1C = interface;
  IScale1CDisp = dispinterface;
  IScaleEvents = dispinterface;

{ Forward declarations: CoClasses }
  Scale45 = IScale1C;

{ АТОЛ: Интерфейс драйвера электронных весов }

  IScale1C = interface(IDispatch)
    ['{DCD43EE8-B38D-11D3-9DE1-0000E8DBEDCE}']
    function Get_ResultCode: Integer; safecall;
    function Get_ResultDescription: WideString; safecall;
    function Get_Version: WideString; safecall;
    function Get_CurrentDeviceIndex: Integer; safecall;
    procedure Set_CurrentDeviceIndex(Value: Integer); safecall;
    function Get_CurrentDeviceNumber: Integer; safecall;
    procedure Set_CurrentDeviceNumber(Value: Integer); safecall;
    function Get_CurrentDeviceName: WideString; safecall;
    procedure Set_CurrentDeviceName(const Value: WideString); safecall;
    function Get_DeviceCount: Integer; safecall;
    function Get_LockDevices: WordBool; safecall;
    procedure Set_LockDevices(Value: WordBool); safecall;
    function Get_DeviceDescription: WideString; safecall;
    function Get_PollingInterval: Integer; safecall;
    procedure Set_PollingInterval(Value: Integer); safecall;
    function Get_EventNumber: Integer; safecall;
    procedure Set_EventNumber(Value: Integer); safecall;
    function Get_DataCount: Integer; safecall;
    function Get_WeightStep: Double; safecall;
    procedure Set_WeightStep(Value: Double); safecall;
    function Get_Model: Integer; safecall;
    procedure Set_Model(Value: Integer); safecall;
    function Get_AsyncMode: WordBool; safecall;
    procedure Set_AsyncMode(Value: WordBool); safecall;
    function Get_PortNumber: Integer; safecall;
    procedure Set_PortNumber(Value: Integer); safecall;
    function Get_BaudRate: Integer; safecall;
    procedure Set_BaudRate(Value: Integer); safecall;
    function Get_Parity: Integer; safecall;
    procedure Set_Parity(Value: Integer); safecall;
    function Get_DeviceEnabled: WordBool; safecall;
    procedure Set_DeviceEnabled(Value: WordBool); safecall;
    function Get_UnitPrice: Currency; safecall;
    procedure Set_UnitPrice(Value: Currency); safecall;
    function Get_SalesPrice: Currency; safecall;
    function Get_Weight: Double; safecall;
    function Get_TareWeight: Double; safecall;
    procedure Set_TareWeight(Value: Double); safecall;
    function Get_NettoWeight: WordBool; safecall;
    function Get_AutoZeroMode: WordBool; safecall;
    function Get_OutOfZero: WordBool; safecall;
    function Get_NegWeight: WordBool; safecall;
    function Get_BigWeight: WordBool; safecall;
    function Get_NonStable: WordBool; safecall;
    function Get_Value: Integer; safecall;
    procedure Set_Value(Value: Integer); safecall;
    function Get_ValuePurpose: Integer; safecall;
    procedure Set_ValuePurpose(Value: Integer); safecall;
    function Get_UProtocolVersion: Integer; safecall;
    function Get_UType: Integer; safecall;
    function Get_UModel: Integer; safecall;
    function Get_UMode: Integer; safecall;
    function Get_UMajorVersion: Integer; safecall;
    function Get_UMinorVersion: Integer; safecall;
    function Get_UDescription: WideString; safecall;
    function Get_UCodePage: Integer; safecall;
    function Get_UBuild: Integer; safecall;
    function Get_UpdatePrice: WordBool; safecall;
    procedure Set_UpdatePrice(Value: WordBool); safecall;
    function Get_InvalidRange: WordBool; safecall;
    function Get_AutoDisable: WordBool; safecall;
    procedure Set_AutoDisable(Value: WordBool); safecall;
    function Get_DataEventEnabled: WordBool; safecall;
    procedure Set_DataEventEnabled(Value: WordBool); safecall;
    function Get_ApplicationHandle: Integer; safecall;
    procedure Set_ApplicationHandle(Value: Integer); safecall;
    function ShowProperties: Integer; safecall;
    function AddDevice: Integer; safecall;
    function DeleteDevice: Integer; safecall;
    function DeleteEvent: Integer; safecall;
    function ReadWeight: Integer; safecall;
    function ZeroScale: Integer; safecall;
    function Tare: Integer; safecall;
    function SetTareWeight: Integer; safecall;
    function GetDeviceMetrics: Integer; safecall;
    function GetValue: Integer; safecall;
    function SetValue: Integer; safecall;
    function Reset: Integer; safecall;
    function GenerateAsyncMessage: Integer; safecall;
    procedure AboutBox; safecall;
    function Get_IsDemo: WordBool; safecall;
    function Get_LogicalNumber: Integer; safecall;
    procedure Set_LogicalNumber(Value: Integer); safecall;
    property ResultCode: Integer read Get_ResultCode;
    property ResultDescription: WideString read Get_ResultDescription;
    property Version: WideString read Get_Version;
    property CurrentDeviceIndex: Integer read Get_CurrentDeviceIndex write Set_CurrentDeviceIndex;
    property CurrentDeviceNumber: Integer read Get_CurrentDeviceNumber write Set_CurrentDeviceNumber;
    property CurrentDeviceName: WideString read Get_CurrentDeviceName write Set_CurrentDeviceName;
    property DeviceCount: Integer read Get_DeviceCount;
    property LockDevices: WordBool read Get_LockDevices write Set_LockDevices;
    property DeviceDescription: WideString read Get_DeviceDescription;
    property PollingInterval: Integer read Get_PollingInterval write Set_PollingInterval;
    property EventNumber: Integer read Get_EventNumber write Set_EventNumber;
    property DataCount: Integer read Get_DataCount;
    property WeightStep: Double read Get_WeightStep write Set_WeightStep;
    property Model: Integer read Get_Model write Set_Model;
    property AsyncMode: WordBool read Get_AsyncMode write Set_AsyncMode;
    property PortNumber: Integer read Get_PortNumber write Set_PortNumber;
    property BaudRate: Integer read Get_BaudRate write Set_BaudRate;
    property Parity: Integer read Get_Parity write Set_Parity;
    property DeviceEnabled: WordBool read Get_DeviceEnabled write Set_DeviceEnabled;
    property UnitPrice: Currency read Get_UnitPrice write Set_UnitPrice;
    property SalesPrice: Currency read Get_SalesPrice;
    property Weight: Double read Get_Weight;
    property TareWeight: Double read Get_TareWeight write Set_TareWeight;
    property NettoWeight: WordBool read Get_NettoWeight;
    property AutoZeroMode: WordBool read Get_AutoZeroMode;
    property OutOfZero: WordBool read Get_OutOfZero;
    property NegWeight: WordBool read Get_NegWeight;
    property BigWeight: WordBool read Get_BigWeight;
    property NonStable: WordBool read Get_NonStable;
    property Value: Integer read Get_Value write Set_Value;
    property ValuePurpose: Integer read Get_ValuePurpose write Set_ValuePurpose;
    property UProtocolVersion: Integer read Get_UProtocolVersion;
    property UType: Integer read Get_UType;
    property UModel: Integer read Get_UModel;
    property UMode: Integer read Get_UMode;
    property UMajorVersion: Integer read Get_UMajorVersion;
    property UMinorVersion: Integer read Get_UMinorVersion;
    property UDescription: WideString read Get_UDescription;
    property UCodePage: Integer read Get_UCodePage;
    property UBuild: Integer read Get_UBuild;
    property UpdatePrice: WordBool read Get_UpdatePrice write Set_UpdatePrice;
    property InvalidRange: WordBool read Get_InvalidRange;
    property AutoDisable: WordBool read Get_AutoDisable write Set_AutoDisable;
    property DataEventEnabled: WordBool read Get_DataEventEnabled write Set_DataEventEnabled;
    property ApplicationHandle: Integer read Get_ApplicationHandle write Set_ApplicationHandle;
    property IsDemo: WordBool read Get_IsDemo;
    property LogicalNumber: Integer read Get_LogicalNumber write Set_LogicalNumber;
  end;

{ DispInterface declaration for Dual Interface IScale1C }

  IScale1CDisp = dispinterface
    ['{DCD43EE8-B38D-11D3-9DE1-0000E8DBEDCE}']
    property ResultCode: Integer readonly dispid 1;
    property ResultDescription: WideString readonly dispid 2;
    property Version: WideString readonly dispid 3;
    property CurrentDeviceIndex: Integer dispid 4;
    property CurrentDeviceNumber: Integer dispid 5;
    property CurrentDeviceName: WideString dispid 6;
    property DeviceCount: Integer readonly dispid 7;
    property LockDevices: WordBool dispid 8;
    property DeviceDescription: WideString readonly dispid 9;
    property PollingInterval: Integer dispid 10;
    property EventNumber: Integer dispid 11;
    property DataCount: Integer readonly dispid 12;
    property WeightStep: Double dispid 13;
    property Model: Integer dispid 14;
    property AsyncMode: WordBool dispid 15;
    property PortNumber: Integer dispid 16;
    property BaudRate: Integer dispid 17;
    property Parity: Integer dispid 18;
    property DeviceEnabled: WordBool dispid 19;
    property UnitPrice: Currency dispid 20;
    property SalesPrice: Currency readonly dispid 21;
    property Weight: Double readonly dispid 22;
    property TareWeight: Double dispid 23;
    property NettoWeight: WordBool readonly dispid 24;
    property AutoZeroMode: WordBool readonly dispid 25;
    property OutOfZero: WordBool readonly dispid 26;
    property NegWeight: WordBool readonly dispid 27;
    property BigWeight: WordBool readonly dispid 28;
    property NonStable: WordBool readonly dispid 29;
    property Value: Integer dispid 30;
    property ValuePurpose: Integer dispid 31;
    property UProtocolVersion: Integer readonly dispid 32;
    property UType: Integer readonly dispid 33;
    property UModel: Integer readonly dispid 34;
    property UMode: Integer readonly dispid 35;
    property UMajorVersion: Integer readonly dispid 36;
    property UMinorVersion: Integer readonly dispid 37;
    property UDescription: WideString readonly dispid 38;
    property UCodePage: Integer readonly dispid 39;
    property UBuild: Integer readonly dispid 40;
    property UpdatePrice: WordBool dispid 41;
    property InvalidRange: WordBool readonly dispid 42;
    property AutoDisable: WordBool dispid 43;
    property DataEventEnabled: WordBool dispid 44;
    property ApplicationHandle: Integer dispid 45;
    function ShowProperties: Integer; dispid 46;
    function AddDevice: Integer; dispid 47;
    function DeleteDevice: Integer; dispid 48;
    function DeleteEvent: Integer; dispid 49;
    function ReadWeight: Integer; dispid 50;
    function ZeroScale: Integer; dispid 51;
    function Tare: Integer; dispid 52;
    function SetTareWeight: Integer; dispid 53;
    function GetDeviceMetrics: Integer; dispid 54;
    function GetValue: Integer; dispid 55;
    function SetValue: Integer; dispid 56;
    function Reset: Integer; dispid 57;
    function GenerateAsyncMessage: Integer; dispid 58;
    procedure AboutBox; dispid -552;
    property IsDemo: WordBool readonly dispid 59;
    property LogicalNumber: Integer dispid 60;
  end;

{ АТОЛ: Интерфейс событий }

  IScaleEvents = dispinterface
    //['{DCD43EE7-B38D-11D3-9DE1-0000E8DBEDCE}']
    ['{CAF4DC44-2819-4CA5-991C-FDFBB66E0A92}']
    procedure DataEvent; dispid 1;
  end;

{ АТОЛ: Драйвер электронных весов }

  TScale45 = class(TOleControl)
  private
    FOnDataEvent: TNotifyEvent;
    FIntf: IScale1C;
    function GetControlInterface: IScale1C;
  protected
    procedure CreateControl;
    procedure InitControlData; override;
    function GetTOleEnumProp(Index: Integer): TOleEnum;
    procedure SetTOleEnumProp(Index: Integer; Value: TOleEnum);
  public
    function ShowProperties: Integer;
    function AddDevice: Integer;
    function DeleteDevice: Integer;
    function DeleteEvent: Integer;
    function ReadWeight: Integer;
    function ZeroScale: Integer;
    function Tare: Integer;
    function SetTareWeight: Integer;
    function GetDeviceMetrics: Integer;
    function GetValue: Integer;
    function SetValue: Integer;
    function Reset: Integer;
    function GenerateAsyncMessage: Integer;
    procedure AboutBox;
    property ControlInterface: IScale1C read GetControlInterface;
    property ResultCode: Integer index 1 read GetIntegerProp;
    property ResultDescription: WideString index 2 read GetWideStringProp;
    property Version: WideString index 3 read GetWideStringProp;
    property DeviceCount: Integer index 7 read GetIntegerProp;
    property DeviceDescription: WideString index 9 read GetWideStringProp;
    property DataCount: Integer index 12 read GetIntegerProp;
    property SalesPrice: Currency index 21 read GetCurrencyProp;
    property Weight: Double index 22 read GetDoubleProp;
    property NettoWeight: WordBool index 24 read GetWordBoolProp;
    property AutoZeroMode: WordBool index 25 read GetWordBoolProp;
    property OutOfZero: WordBool index 26 read GetWordBoolProp;
    property NegWeight: WordBool index 27 read GetWordBoolProp;
    property BigWeight: WordBool index 28 read GetWordBoolProp;
    property NonStable: WordBool index 29 read GetWordBoolProp;
    property UProtocolVersion: Integer index 32 read GetIntegerProp;
    property UType: Integer index 33 read GetIntegerProp;
    property UModel: Integer index 34 read GetIntegerProp;
    property UMode: Integer index 35 read GetIntegerProp;
    property UMajorVersion: Integer index 36 read GetIntegerProp;
    property UMinorVersion: Integer index 37 read GetIntegerProp;
    property UDescription: WideString index 38 read GetWideStringProp;
    property UCodePage: Integer index 39 read GetIntegerProp;
    property UBuild: Integer index 40 read GetIntegerProp;
    property InvalidRange: WordBool index 42 read GetWordBoolProp;
    property IsDemo: WordBool index 59 read GetWordBoolProp;
  published
    property CurrentDeviceIndex: Integer index 4 read GetIntegerProp write SetIntegerProp stored False;
    property CurrentDeviceNumber: Integer index 5 read GetIntegerProp write SetIntegerProp stored False;
    property CurrentDeviceName: WideString index 6 read GetWideStringProp write SetWideStringProp stored False;
    property LockDevices: WordBool index 8 read GetWordBoolProp write SetWordBoolProp stored False;
    property PollingInterval: Integer index 10 read GetIntegerProp write SetIntegerProp stored False;
    property EventNumber: Integer index 11 read GetIntegerProp write SetIntegerProp stored False;
    property WeightStep: Double index 13 read GetDoubleProp write SetDoubleProp stored False;
    property Model: Integer index 14 read GetIntegerProp write SetIntegerProp stored False;
    property AsyncMode: WordBool index 15 read GetWordBoolProp write SetWordBoolProp stored False;
    property PortNumber: Integer index 16 read GetIntegerProp write SetIntegerProp stored False;
    property BaudRate: Integer index 17 read GetIntegerProp write SetIntegerProp stored False;
    property Parity: Integer index 18 read GetIntegerProp write SetIntegerProp stored False;
    property DeviceEnabled: WordBool index 19 read GetWordBoolProp write SetWordBoolProp stored False;
    property UnitPrice: Currency index 20 read GetCurrencyProp write SetCurrencyProp stored False;
    property TareWeight: Double index 23 read GetDoubleProp write SetDoubleProp stored False;
    property Value: Integer index 30 read GetIntegerProp write SetIntegerProp stored False;
    property ValuePurpose: Integer index 31 read GetIntegerProp write SetIntegerProp stored False;
    property UpdatePrice: WordBool index 41 read GetWordBoolProp write SetWordBoolProp stored False;
    property AutoDisable: WordBool index 43 read GetWordBoolProp write SetWordBoolProp stored False;
    property DataEventEnabled: WordBool index 44 read GetWordBoolProp write SetWordBoolProp stored False;
    property ApplicationHandle: Integer index 45 read GetIntegerProp write SetIntegerProp stored False;
    property LogicalNumber: Integer index 60 read GetIntegerProp write SetIntegerProp stored False;
    property OnDataEvent: TNotifyEvent read FOnDataEvent write FOnDataEvent;
  end;

procedure Register;

implementation

uses ComObj;

procedure TScale45.InitControlData;
const
  CEventDispIDs: array[0..0] of Integer = (
    $00000001);
  CControlData: TControlData = (
    //ClassID: '{DCD43EE6-B38D-11D3-9DE1-0000E8DBEDCE}';
    //EventIID: '{DCD43EE7-B38D-11D3-9DE1-0000E8DBEDCE}';
    ClassID: '{CAF4DC44-2819-4CA5-991C-FDFBB66E0A92}';
    EventIID: '{CAF4DC44-2819-4CA5-991C-FDFBB66E0A92}';
    EventCount: 1;
    EventDispIDs: @CEventDispIDs;
    LicenseKey: nil;
    Flags: $00000000;
    Version: 300);
begin
  ControlData := @CControlData;
end;

procedure TScale45.CreateControl;

  procedure DoCreate;
  begin
    FIntf := IUnknown(OleObject) as IScale1C;
  end;

begin
  if FIntf = nil then DoCreate;
end;

function TScale45.GetControlInterface: IScale1C;
begin
  CreateControl;
  Result := FIntf;
end;

function TScale45.GetTOleEnumProp(Index: Integer): TOleEnum;
begin
  Result := GetIntegerProp(Index);
end;

procedure TScale45.SetTOleEnumProp(Index: Integer; Value: TOleEnum);
begin
  SetIntegerProp(Index, Value);
end;

function TScale45.ShowProperties: Integer;
begin
  CreateControl;
  Result := FIntf.ShowProperties;
end;

function TScale45.AddDevice: Integer;
begin
  CreateControl;
  Result := FIntf.AddDevice;
end;

function TScale45.DeleteDevice: Integer;
begin
  CreateControl;
  Result := FIntf.DeleteDevice;
end;

function TScale45.DeleteEvent: Integer;
begin
  CreateControl;
  Result := FIntf.DeleteEvent;
end;

function TScale45.ReadWeight: Integer;
begin
  CreateControl;
  Result := FIntf.ReadWeight;
end;

function TScale45.ZeroScale: Integer;
begin
  CreateControl;
  Result := FIntf.ZeroScale;
end;

function TScale45.Tare: Integer;
begin
  CreateControl;
  Result := FIntf.Tare;
end;

function TScale45.SetTareWeight: Integer;
begin
  CreateControl;
  Result := FIntf.SetTareWeight;
end;

function TScale45.GetDeviceMetrics: Integer;
begin
  CreateControl;
  Result := FIntf.GetDeviceMetrics;
end;

function TScale45.GetValue: Integer;
begin
  CreateControl;
  Result := FIntf.GetValue;
end;

function TScale45.SetValue: Integer;
begin
  CreateControl;
  Result := FIntf.SetValue;
end;

function TScale45.Reset: Integer;
begin
  CreateControl;
  Result := FIntf.Reset;
end;

function TScale45.GenerateAsyncMessage: Integer;
begin
  CreateControl;
  Result := FIntf.GenerateAsyncMessage;
end;

procedure TScale45.AboutBox;
begin
  CreateControl;
  FIntf.AboutBox;
end;


procedure Register;
begin
  RegisterComponents('ActiveX', [TScale45]);
end;

end.
