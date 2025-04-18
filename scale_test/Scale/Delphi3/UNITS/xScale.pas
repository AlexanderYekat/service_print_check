unit xScale;

interface

Uses
  // VCL
  Windows, ComObj, SysUtils,
  // This
  Scale1C_TLB;

const
  SCALE_MODEL_VTPOS         = 5;
  SCALE_MODEL_VTMINIPOS     = 6;

type
  { TScaleX }

  TScaleX = class
  private
    FControlInterface: IScale1C;
    procedure RaiseLastError;
  protected
    function GetResultCode: Integer;
    function GetResultDescription: String;
    function GetCurrentDeviceIndex: Integer;
    procedure SetCurrentDeviceIndex(const Value: Integer);
    function GetCurrentDeviceNumber: Integer;
    procedure SetCurrentDeviceNumber(const Value: Integer);
    function GetCurrentDeviceName: String;
    procedure SetCurrentDeviceName(const Value: String);
    function GetDeviceCount: Integer;
    function Get_PortNumber: Integer;
    procedure Set_PortNumber(Value: Integer);
    function Get_BaudRate: Integer;
    procedure Set_BaudRate(Value: Integer);
    function Get_Parity: Integer;
    procedure Set_Parity(Value: Integer);
    function Get_LockDevices: Boolean;
    procedure Set_LockDevices(Value: Boolean);
    function Get_DeviceDescription: WideString;
    function Get_DeviceEnabled: Boolean;
    procedure Set_DeviceEnabled(Value: Boolean);
    function Get_UnitPrice: Currency;
    procedure Set_UnitPrice(Value: Currency);
    function Get_TareWeight: Double;
    procedure Set_TareWeight(Value: Double);
    function Get_SalesPrice: Currency;
    function Get_Weight: Double;
    function Get_Model: Integer;
    procedure Set_Model(Value: Integer);
    function GetVersion: String;
    function Get_UProtocolVersion: Integer;
    function Get_UType: Integer;
    function Get_UModel: Integer;
    function Get_UMode: Integer;
    function Get_UMajorVersion: Integer;
    function Get_UMinorVersion: Integer;
    function Get_UBuild: Integer;
    function Get_UCodePage: Integer;
    function Get_UDescription: WideString;

    function Get_NettoWeight: Boolean;
    function Get_AutoZeroMode: Boolean;
    function Get_OutOfZero: Boolean;
    function Get_NegWeight: Boolean;
    function Get_BigWeight: Boolean;
    function Get_NonStable: Boolean;
    function Get_InvalidRange: Boolean;

    function Get_Value: Integer;
    procedure Set_Value(Value: Integer);
    function Get_ValuePurpose: Integer;
    procedure Set_ValuePurpose(Value: Integer);

    function Get_AsyncMode: Boolean;
    procedure Set_AsyncMode(Value: Boolean);
    function Get_PollingInterval: Integer;
    procedure Set_PollingInterval(Value: Integer);
    function Get_EventNumber: Integer;
    procedure Set_EventNumber(Value: Integer);
    function Get_DataCount: Integer;
    function Get_WeightStep: Double;
    procedure Set_WeightStep(Value: Double);
    function Get_UpdatePrice: Boolean;
    procedure Set_UpdatePrice(const Value: Boolean);

    function Get_DataEventEnabled: Boolean;
    procedure Set_DataEventEnabled(const Value: Boolean);
    function Get_AutoDisable: Boolean;
    procedure Set_AutoDisable(const Value: Boolean);
  public
    constructor Create(AControlInterface: IScale1C);
    destructor Destroy; override;

    function AddDevice: Integer;
    procedure DeleteDevice;

    procedure ReadWeight;
    procedure ZeroScale;
    procedure Tare;
    procedure SetTareWeight;
    procedure GetDeviceMetrics;
    procedure Reset;
    procedure AboutBox;
    procedure ShowProperties;
    procedure GetValue;
    procedure SetValue;
    procedure DeleteEvent;
    procedure GenerateAsyncMessage;

    property ResultCode: Integer read GetResultCode;
    property ResultDescription: String read GetResultDescription;
    property CurrentDeviceIndex: Integer read GetCurrentDeviceIndex write SetCurrentDeviceIndex;
    property CurrentDeviceNumber: Integer read GetCurrentDeviceNumber write SetCurrentDeviceNumber;
    property CurrentDeviceName: String read GetCurrentDeviceName write SetCurrentDeviceName;
    property DeviceCount: Integer read GetDeviceCount;
    property PortNumber: Integer read Get_PortNumber write Set_PortNumber;
    property BaudRate: Integer read Get_BaudRate write Set_BaudRate;
    property Parity: Integer read Get_Parity write Set_Parity;
    property LockDevices: Boolean read Get_LockDevices write Set_LockDevices;
    property DeviceDescription: WideString read Get_DeviceDescription;
    property DeviceEnabled: Boolean read Get_DeviceEnabled write Set_DeviceEnabled;
    property UnitPrice: Currency read Get_UnitPrice write Set_UnitPrice;
    property TareWeight: Double read Get_TareWeight write Set_TareWeight;
    property SalesPrice: Currency read Get_SalesPrice;
    property Weight: Double read Get_Weight;
    property Model: Integer read Get_Model write Set_Model;
    property Version: String read GetVersion;
    property UProtocolVersion: Integer read Get_UProtocolVersion;
    property UType: Integer read Get_UType;
    property UModel: Integer read Get_UModel;
    property UMode: Integer read Get_UMode;
    property UMajorVersion: Integer read Get_UMajorVersion;
    property UMinorVersion: Integer read Get_UMinorVersion;
    property UBuild: Integer read Get_UBuild;
    property UCodePage: Integer read Get_UCodePage;
    property UDescription: WideString read Get_UDescription;
    property NettoWeight: Boolean read Get_NettoWeight;
    property AutoZeroMode: Boolean read Get_AutoZeroMode;
    property OutOfZero: Boolean read Get_OutOfZero;
    property NegWeight: Boolean read Get_NegWeight;
    property BigWeight: Boolean read Get_BigWeight;
    property NonStable: Boolean read Get_NonStable;
    property InvalidRange: Boolean read Get_InvalidRange;
    property Value: Integer read Get_Value write Set_Value;
    property ValuePurpose: Integer read Get_ValuePurpose write Set_ValuePurpose;
    property AsyncMode: Boolean read Get_AsyncMode write Set_AsyncMode;
    property PollingInterval: Integer read Get_PollingInterval write Set_PollingInterval;
    property EventNumber: Integer read Get_EventNumber write Set_EventNumber;
    property DataCount: Integer read Get_DataCount;
    property WeightStep: Double read Get_WeightStep write Set_WeightStep;
    property UpdatePrice: Boolean read Get_UpdatePrice write Set_UpdatePrice;
    property DataEventEnabled: Boolean read Get_DataEventEnabled write Set_DataEventEnabled;
    property AutoDisable: Boolean read Get_AutoDisable write Set_AutoDisable;
    property ControlInterface: IScale1C read FControlInterface;
  end;

  { EScaleError }

  EScaleError = class(Exception)
  private
   FErrorCode: integer;
  public
    property ErrorCode: integer read fErrorCode;
    constructor CreateByCode(ACode: integer; const AMessage: string);
  end;

implementation

{ EScaleError }

constructor EScaleError.CreateByCode(ACode: Integer;
  const AMessage: string);
begin
  inherited Create(AMessage);
  FErrorCode := ACode;
end;

{ TScaleX }

constructor TScaleX.Create(AControlInterface: IScale1C);
begin
  inherited Create;
  FControlInterface := AControlInterface;
end;

destructor TScaleX.Destroy;
begin
  FControlInterface := nil;
  inherited Destroy;
end;

procedure TScaleX.RaiseLastError;
begin
  if ResultCode <> 0 then
    Raise EScaleError.CreateByCode(ResultCode, ResultDescription);
end;

function TScaleX.AddDevice: Integer;
begin
  Result := ControlInterface.AddDevice;
  RaiseLastError;
end;

procedure TScaleX.DeleteDevice;
begin
  ControlInterface.DeleteDevice;
  RaiseLastError;
end;

function TScaleX.GetCurrentDeviceIndex: Integer;
begin
  Result := ControlInterface.CurrentDeviceIndex;
end;

function TScaleX.GetCurrentDeviceName: String;
begin
  Result := ControlInterface.CurrentDeviceName;
end;

function TScaleX.GetCurrentDeviceNumber: Integer;
begin
  Result := ControlInterface.CurrentDeviceNumber;
end;

function TScaleX.GetDeviceCount: Integer;
begin
  Result := ControlInterface.DeviceCount;
end;

function TScaleX.GetResultCode: Integer;
begin
  Result := ControlInterface.ResultCode;
end;

function TScaleX.GetResultDescription: String;
begin
  Result := ControlInterface.ResultDescription;
end;

procedure TScaleX.SetCurrentDeviceIndex(const Value: Integer);
begin
  ControlInterface.CurrentDeviceIndex := Value;
  RaiseLastError;
end;

procedure TScaleX.SetCurrentDeviceName(const Value: String);
begin
  ControlInterface.CurrentDeviceName := Value;
  RaiseLastError;
end;

procedure TScaleX.SetCurrentDeviceNumber(const Value: Integer);
begin
  ControlInterface.CurrentDeviceNumber := Value;
  RaiseLastError;
end;

function TScaleX.Get_PortNumber: Integer;
begin
  Result := ControlInterface.PortNumber;
  RaiseLastError;
end;

procedure TScaleX.Set_PortNumber(Value: Integer);
begin
  ControlInterface.PortNumber := Value;
  RaiseLastError;
end;

function TScaleX.Get_BaudRate: Integer;
begin
  Result := ControlInterface.BaudRate;
  RaiseLastError;
end;

procedure TScaleX.Set_BaudRate(Value: Integer);
begin
  ControlInterface.BaudRate := Value;
  RaiseLastError;
end;

function TScaleX.Get_Parity: Integer;
begin
  Result := ControlInterface.Parity;
  RaiseLastError;
end;

procedure TScaleX.Set_Parity(Value: Integer);
begin
  ControlInterface.Parity := Value;
  RaiseLastError;
end;

function TScaleX.Get_LockDevices: Boolean;
begin
  Result := ControlInterface.LockDevices;
  RaiseLastError;
end;

procedure TScaleX.Set_LockDevices(Value: Boolean);
begin
  ControlInterface.LockDevices := Value;
  RaiseLastError;
end;

function TScaleX.Get_DeviceDescription: WideString;
begin
  Result := ControlInterface.DeviceDescription;
  RaiseLastError;
end;

function TScaleX.Get_DeviceEnabled: Boolean;
begin
  Result := ControlInterface.DeviceEnabled;
  RaiseLastError;
end;

procedure TScaleX.Set_DeviceEnabled(Value: Boolean);
begin
  ControlInterface.DeviceEnabled := Value;
  RaiseLastError;
end;

function TScaleX.Get_UnitPrice: Currency;
begin
  Result := ControlInterface.UnitPrice;
  RaiseLastError;
end;

procedure TScaleX.Set_UnitPrice(Value: Currency);
begin
  ControlInterface.UnitPrice := Value;
  RaiseLastError;
end;

function TScaleX.Get_TareWeight: Double;
begin
  Result := ControlInterface.TareWeight;
  RaiseLastError;
end;

procedure TScaleX.Set_TareWeight(Value: Double);
begin
  ControlInterface.TareWeight := Value;
  RaiseLastError;
end;

function TScaleX.Get_SalesPrice: Currency;
begin
  Result := ControlInterface.SalesPrice;
  RaiseLastError;
end;

function TScaleX.Get_Weight: Double;
begin
  Result := ControlInterface.Weight;
  RaiseLastError;
end;

function TScaleX.Get_Model: Integer;
begin
  Result := ControlInterface.Model;
  RaiseLastError;
end;

procedure TScaleX.Set_Model(Value: Integer);
begin
  ControlInterface.MOdel := Value;
  RaiseLastError;
end;

procedure TScaleX.ReadWeight;
begin
  ControlInterface.ReadWeight;
  RaiseLastError;
end;

procedure TScaleX.ZeroScale;
begin
  ControlInterface.ZeroScale;
  RaiseLastError;
end;

procedure TScaleX.Tare;
begin
  ControlInterface.Tare;
  RaiseLastError;
end;

procedure TScaleX.SetTareWeight;
begin
  ControlInterface.SetTareWeight;
  RaiseLastError;
end;

procedure TScaleX.GetDeviceMetrics;
begin
  ControlInterface.GetDeviceMetrics;
  RaiseLastError;
end;

procedure TScaleX.Reset;
begin
  ControlInterface.Reset;
  RaiseLastError;
end;

procedure TScaleX.AboutBox;
begin
  ControlInterface.AboutBox;
  RaiseLastError;
end;

function TScaleX.GetVersion: String;
begin
  Result := ControlInterface.Version;
  RaiseLastError;
end;

procedure TScaleX.ShowProperties;
begin
  ControlInterface.ShowProperties;
  RaiseLastError;
end;

function TScaleX.Get_UProtocolVersion: Integer;
begin
  Result := ControlInterface.UProtocolVersion;
  RaiseLastError;
end;

function TScaleX.Get_UType: Integer;
begin
  Result := ControlInterface.UType;
  RaiseLastError;
end;

function TScaleX.Get_UModel: Integer;
begin
  Result := ControlInterface.UModel;
  RaiseLastError;
end;

function TScaleX.Get_UMode: Integer;
begin
  Result := ControlInterface.UMode;
  RaiseLastError;
end;

function TScaleX.Get_UMajorVersion: Integer;
begin
  Result := ControlInterface.UMajorVersion;
  RaiseLastError;
end;

function TScaleX.Get_UMinorVersion: Integer;
begin
  Result := ControlInterface.UMinorVersion;
  RaiseLastError;
end;

function TScaleX.Get_UBuild: Integer;
begin
  Result := ControlInterface.UBuild;
  RaiseLastError;
end;

function TScaleX.Get_UCodePage: Integer;
begin
  Result := ControlInterface.UCodePage;
  RaiseLastError;
end;

function TScaleX.Get_UDescription: WideString;
begin
  Result := ControlInterface.UDescription;
  RaiseLastError;
end;

function TScaleX.Get_NettoWeight: Boolean;
begin
  Result := ControlInterface.NettoWeight;
  RaiseLastError;
end;

function TScaleX.Get_AutoZeroMode: Boolean;
begin
  Result := ControlInterface.AutoZeroMode;
  RaiseLastError;
end;

function TScaleX.Get_OutOfZero: Boolean;
begin
  Result := ControlInterface.OutOfZero;
  RaiseLastError;
end;

function TScaleX.Get_NegWeight: Boolean;
begin
  Result := ControlInterface.NegWeight;
  RaiseLastError;
end;

function TScaleX.Get_BigWeight: Boolean;
begin
  Result := ControlInterface.BigWeight;
  RaiseLastError;
end;

function TScaleX.Get_NonStable: Boolean;
begin
  Result := ControlInterface.NonStable;
  RaiseLastError;
end;

function TScaleX.Get_Value: Integer;
begin
  Result := ControlInterface.Value;
  RaiseLastError;
end;

procedure TScaleX.Set_Value(Value: Integer);
begin
  ControlInterface.Value := Value;
  RaiselastError;
end;

function TScaleX.Get_ValuePurpose: Integer;
begin
  Result := ControlInterface.ValuePurpose;
  RaiseLastError;
end;

procedure TScaleX.Set_ValuePurpose(Value: Integer);
begin
  ControlInterface.ValuePurpose := Value;
  RaiseLastError;
end;

procedure TScaleX.GetValue;
begin
  ControlInterface.GetValue;
  RaiseLastError;
end;

procedure TScaleX.SetValue;
begin
  ControlInterface.SetValue;
  RaiseLastError;
end;

function TScaleX.Get_AsyncMode: Boolean;
begin
  Result := ControlInterface.AsyncMode;
  RaiseLastError;
end;

procedure TScaleX.Set_AsyncMode(Value: Boolean);
begin
  ControlInterface.AsyncMode := Value;
  RaiseLastError;
end;

function TScaleX.Get_PollingInterval: Integer;
begin
  Result := ControlInterface.PollingInterval;
  RaiseLastError;
end;

procedure TScaleX.Set_PollingInterval(Value: Integer);
begin
  ControlInterface.PollingInterval := Value;
  RaiseLastError;
end;

function TScaleX.Get_EventNumber: Integer;
begin
  Result := ControlInterface.EventNumber;
  RaiseLastError;
end;

procedure TScaleX.Set_EventNumber(Value: Integer);
begin
  ControlInterface.EventNumber := Value;
  RaiseLastError;
end;

function TScaleX.Get_DataCount: Integer;
begin
  Result := ControlInterface.DataCount;
  RaiseLastError;
end;

function TScaleX.Get_WeightStep: Double;
begin
  Result := ControlInterface.WeightStep;
  RaiseLastError;
end;

procedure TScaleX.Set_WeightStep(Value: Double);
begin
  ControlInterface.WeightStep := Value;
  RaiseLastError;
end;

function TScaleX.Get_UpdatePrice: Boolean;
begin
  Result := ControlInterface.UpdatePrice;
  RaiseLastError;
end;

procedure TScaleX.Set_UpdatePrice(const Value: Boolean);
begin
  ControlInterface.UpdatePrice := Value;
  RaiseLastError;
end;

function TScaleX.Get_InvalidRange: Boolean;
begin
  Result := ControlInterface.InvalidRange;
  RaiseLastError;
end;

function TScaleX.Get_DataEventEnabled: Boolean;
begin
  Result := ControlInterface.DataEventEnabled;
  RaiseLastError;
end;

procedure TScaleX.Set_DataEventEnabled(const Value: Boolean);
begin
  ControlInterface.DataEventEnabled := Value;
  RaiseLastError;
end;

function TScaleX.Get_AutoDisable: Boolean;
begin
  Result := ControlInterface.AutoDisable;
  RaiseLastError;
end;

procedure TScaleX.Set_AutoDisable(const Value: Boolean);
begin
  ControlInterface.AutoDisable := Value;
  RaiseLastError;
end;

procedure TScaleX.DeleteEvent;
begin
  ControlInterface.DeleteEvent;
  RaiseLastError;
end;

procedure TScaleX.GenerateAsyncMessage;
begin
  ControlInterface.GenerateAsyncMessage;
  RaiseLastError;
end;

end.
