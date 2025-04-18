unit fmuDevice;

interface

uses
  // VCL
  Windows, Messages, SysUtils, Classes, Graphics, Controls, Forms, Dialogs,
  StdCtrls, ComCtrls, ExtCtrls,
  // This
  xScale,
  Scale1C_TLB;

type
  { TfmDevices }

  TfmDevices = class(TForm)
    Caption1: TLabel;
    Caption2: TLabel;
    Bevel1: TBevel;
    Shape1: TShape;
    Label1: TLabel;
    Label14: TLabel;
    Label15: TLabel;
    edtResultDescription: TEdit;
    edtResultCode: TEdit;
    btnAdd: TButton;
    btnDelete: TButton;
    btnClose: TButton;
    topPanel: TPanel;
    Label4: TLabel;
    Label2: TLabel;
    Label3: TLabel;
    cbDeviceNumber: TComboBox;
    cbDeviceIndex: TComboBox;
    edtDeviceCount: TEdit;
    bottomPanel: TPanel;
    Shape2: TShape;
    Label9: TLabel;
    Label12: TLabel;
    Label13: TLabel;
    Label6: TLabel;
    Label19: TLabel;
    chkDeviceEnabled: TCheckBox;
    cboBaudRate: TComboBox;
    cboPortNumber: TComboBox;
    edtDeviceName: TEdit;
    udPortNumber: TUpDown;
    udBaudRate: TUpDown;
    edtPortNumber: TEdit;
    edtBaudRate: TEdit;
    cboParity: TComboBox;
    cboModelID: TComboBox;
    edtParity: TEdit;
    edtModelID: TEdit;
    udParity: TUpDown;
    udModelID: TUpDown;
    procedure EditorKeyDown(Sender: TObject; var Key: Word;
      Shift: TShiftState);
    procedure EditorKeyPress(Sender: TObject; var Key: Char);
    procedure FormCreate(Sender: TObject);
    procedure edtDeviceNameExit(Sender: TObject);
    procedure DeviceIndexClick(Sender: TObject);
    procedure DeviceNumberClick(Sender: TObject);
    procedure btnAddClick(Sender: TObject);
    procedure btnDeleteClick(Sender: TObject);
    procedure cboPortNumberChange(Sender: TObject);
    procedure edtPortNumberExit(Sender: TObject);
    procedure udPortNumberClick(Sender: TObject; Button: TUDBtnType);
    procedure cboBaudRateChange(Sender: TObject);
    procedure edtBaudRateExit(Sender: TObject);
    procedure udBaudRateClick(Sender: TObject; Button: TUDBtnType);
    procedure cboParityChange(Sender: TObject);
    procedure edtParityExit(Sender: TObject);
    procedure udParityClick(Sender: TObject; Button: TUDBtnType);
    procedure cboModelIDChange(Sender: TObject);
    procedure edtModelIDExit(Sender: TObject);
    procedure udModelIDClick(Sender: TObject; Button: TUDBtnType);
    procedure chkDeviceEnabledClick(Sender: TObject);
    procedure FormDestroy(Sender: TObject);
    procedure btnCloseClick(Sender: TObject);
  private
    procedure GetDeviceName;
    procedure SetDeviceName;
  private
    procedure GetDeviceIndex;
    procedure SetDeviceIndex;
  private
    procedure GetDeviceNumber;
    procedure SetDeviceNumber;
  private
    procedure GetLastResult;
    procedure GetDeviceCount;
  private
    procedure GetPortNumber;
    procedure SetUDPortNumber;
    procedure SetEditPortNumber;
    procedure SetComboPortNumber;
  private
    procedure GetBaudRate;
    procedure SetUDBaudRate;
    procedure SetEditBaudRate;
    procedure SetComboBaudRate;
  private
    procedure GetParity;
    procedure SetUDParity;
    procedure SetEditParity;
    procedure SetComboParity;
  private
    procedure GetModelID;
    procedure SetUDModelID;
    procedure SetEditModelID;
    procedure SetComboModelID;
  private
    procedure GetDeviceEnabled;
    procedure SetDeviceEnabled;
  private
    SaveApplicationOnException: TExceptionEvent;
    procedure ApplicationException(Sender: TObject; E: Exception);
  private
    FScale: TScaleX;
    procedure UpdateForm;
    procedure Init(const AScale: TScaleX; AFont: TFont);
  end;

procedure ShowDevicesProperties(const Scale: TScaleX; Font: TFont);

implementation

{$R *.DFM}

procedure ShowDevicesProperties(const Scale: TScaleX; Font: TFont);
var
  D: TfmDevices;
begin
  D := TfmDevices.Create(nil);
  try
    D.Init(Scale, Font);
    D.ShowModal;
  finally
    D.Free;
  end;
end;

procedure SafeSetChecked(CheckBox: TCheckBox; Value: Boolean);
var
  SaveOnClick: TNotifyEvent;
begin
  SaveOnClick := CheckBox.OnClick;
  CheckBox.OnClick := nil;
  try
    CheckBox.Checked := Value;
  finally
    CheckBox.OnClick := SaveOnClick;
  end;
end;

procedure SafeSetComboBox(ComboBox: TComboBox; Value: Integer);
var
  SaveOnChange: TNotifyEvent;
begin
  SaveOnChange := ComboBox.OnChange;
  ComboBox.OnChange := nil;
  try
    ComboBox.ItemIndex := Value;
  finally
    ComboBox.OnChange := SaveOnChange;
  end;
end;

function StrToIntRus(const Value: String): Integer;
begin
  try
    Result := StrToInt(Value);
  except
    raise EConvertError.CreateFmt('"%s" не является целым числом', [Value]);
  end;
end;

{ TfmDevices }

procedure TfmDevices.Init(const AScale: TScaleX; AFont: TFont);
begin
  fScale := AScale;
  Font := AFont;
  UpdateForm;
end;

procedure TfmDevices.UpdateForm;
begin
  GetLastResult;
  GetDeviceName;
  GetDeviceIndex;
  GetDeviceNumber;
  GetDeviceEnabled;
  GetDeviceCount;
  GetPortNumber;
  GetBaudRate;
  GetParity;
  GetModelID;
end;

procedure TfmDevices.GetDeviceCount;
begin
  try
    edtDeviceCount.Text := IntToStr(fScale.DeviceCount);
  except
    on E: Exception do
     edtDeviceCount.Text := E.Message;
  end;
end;

procedure TfmDevices.GetLastResult;
begin
  edtResultCode.Text := IntToStr(fScale.ResultCode);
  edtResultDescription.Text := fScale.ResultDescription;
end;

// DeviceName

procedure TfmDevices.GetDeviceName;
begin
  try
    edtDeviceName.Text := fScale.CurrentDeviceName;
  except
    on E: Exception do edtDeviceName.Text := E.Message;
  end;
end;

procedure TfmDevices.SetDeviceName;
begin
  fScale.CurrentDeviceName := edtDeviceName.Text;
end;

// DeviceIndex

procedure TfmDevices.GetDeviceIndex;
begin
  try
    SafeSetComboBox(cbDeviceIndex, fScale.CurrentDeviceIndex);
  except

  end;
end;

procedure TfmDevices.SetDeviceIndex;
begin
  fScale.CurrentDeviceIndex := cbDeviceIndex.ItemIndex;
end;

// DeviceNumber

procedure TfmDevices.GetDeviceNumber;
begin
  try
    SafeSetComboBox(cbDeviceNumber, fScale.CurrentDeviceNumber-1);
  except

  end;
end;

procedure TfmDevices.SetDeviceNumber;
begin
  fScale.CurrentDeviceNumber := cbDeviceNumber.ItemIndex+1;
end;

// PortNumber

procedure TfmDevices.GetPortNumber;
var
  Value: Integer;
begin
  try
    Value := fScale.PortNumber;
    SafeSetComboBox(cboPortNumber,
      cboPortNumber.Items.IndexOfObject(TObject(Value)));
    edtPortNumber.Text := IntToStr(Value);
    udPortNumber.Position := Value;
  except
    on E: Exception do edtPortNumber.Text := E.Message;
  end;
end;

procedure TfmDevices.SetUDPortNumber;
begin
  fScale.PortNumber := udPortNumber.Position;
end;

procedure TfmDevices.SetEditPortNumber;
begin
  fScale.PortNumber := StrToIntRus(edtPortNumber.Text);
end;

procedure TfmDevices.SetComboPortNumber;
begin
  with cboPortNumber do
   fScale.PortNumber := Integer(Items.Objects[ItemIndex]);
end;

// BaudRate

procedure TfmDevices.GetBaudRate;
var
  Value: Integer;
begin
  try
    Value := fScale.BaudRate;
    SafeSetComboBox(cboBaudRate,
      cboBaudRate.Items.IndexOfObject(TObject(Value)));
    udBaudRate.Position := Value;
    edtBaudRate.Text := IntToStr(Value);
  except
    on E: Exception do edtBaudRate.Text := E.Message;
  end;
end;

procedure TfmDevices.SetUDBaudRate;
begin
  fScale.BaudRate := udBaudRate.Position;
end;

procedure TfmDevices.SetEditBaudRate;
begin
  fScale.BaudRate := StrToIntRus(edtBaudRate.Text);
end;

procedure TfmDevices.SetComboBaudRate;
begin
  with cboBaudRate do
   fScale.BaudRate := Integer(Items.Objects[ItemIndex]);
end;

// Parity

procedure TfmDevices.GetParity;
var
  Value: Integer;
begin
  try
    Value := fScale.Parity;
    SafeSetComboBox(cboParity,
      cboParity.Items.IndexOfObject(TObject(Value)));
    udParity.Position := Value;
    edtParity.Text := IntToStr(Value);
  except
    on E: Exception do edtParity.Text := E.Message;
  end;
end;

procedure TfmDevices.SetUDParity;
begin
  fScale.Parity := udParity.Position;
end;

procedure TfmDevices.SetEditParity;
begin
  fScale.Parity := StrToIntRus(edtParity.Text);
end;

procedure TfmDevices.SetComboParity;
begin
  with cboParity do
   fScale.Parity := Integer(Items.Objects[ItemIndex]);
end;

// ModelID

procedure TfmDevices.GetModelID;
var
  Value: Integer;
begin
  try
    Value := fScale.Model;
    SafeSetComboBox(cboModelID,
      cboModelID.Items.IndexOfObject(TObject(Value)));
    udModelID.Position := Value;
    edtModelID.Text := IntToStr(Value);
  except
    on E: Exception do edtModelID.Text := E.Message;
  end;
end;

procedure TfmDevices.SetUDModelID;
begin
  fScale.Model := udModelID.Position;
end;

procedure TfmDevices.SetEditModelID;
begin
  fScale.Model := StrToIntRus(edtModelID.Text);
end;

procedure TfmDevices.SetComboModelID;
begin
  with cboModelID do
    fScale.Model := Integer(Items.Objects[ItemIndex]);
end;

// DeviceEnabled

procedure TfmDevices.GetDeviceEnabled;
begin
  SafeSetChecked(chkDeviceEnabled, fScale.DeviceEnabled);
end;

procedure TfmDevices.SetDeviceEnabled;
begin
  fScale.DeviceEnabled := chkDeviceEnabled.Checked;
end;

{ Обработчики событий }

procedure TfmDevices.EditorKeyDown(Sender: TObject; var Key: Word;
  Shift: TShiftState);
begin
  case Key of
  vk_Return:
    (Sender as TEdit).OnExit(Sender);
  end;
end;

procedure TfmDevices.EditorKeyPress(Sender: TObject; var Key: Char);
begin
  if Key in [#13, #27] then
  begin
    Key := #0;
  end
end;

procedure TfmDevices.FormCreate(Sender: TObject);
type
  TComboItem = record
    ItemName: String;
    ItemValue: Integer
  end;

  procedure FillComboBox(ComboBox: TComboBox;
    const ComboItems: array of TComboItem);
  var
    I: Integer;
    cboItem: TComboItem;
  begin
    with ComboBox.Items do
    begin
      BeginUpdate;
      try
        Clear;
        for I := Low(ComboItems) to High(ComboItems) do
        begin
          cboItem := ComboItems[I];
          AddObject(cboItem.ItemName, TObject(cboItem.ItemValue));
        end;
      finally
        EndUpdate;
      end;
    end;
  end;

  procedure FillPortNumber;
  var
    I: Integer;
  begin
    with cboPortNumber.Items do
    begin
      BeginUpdate;
      try
        Clear;
        for I := 1 to 32 do
        begin
          AddObject('COM ' + IntToStr(I), TObject(I));
        end;
      finally
        EndUpdate;
      end;
    end;
  end;

  procedure FillBaudRate;
  const
    BaudRates: array [0..3] of TComboItem =
      (
       (ItemName: '1200'; ItemValue: 3),
       (ItemName: '2400'; ItemValue: 4),
       (ItemName: '4800'; ItemValue: 5),
       (ItemName: '9600'; ItemValue: 7)
       );
  begin
    FillComboBox(cboBaudRate, BaudRates);
  end;

  procedure FillParity;
  const
    Parities: array [0..1] of TComboItem =
      (
       (ItemName: 'Нет';        ItemValue: 0),
       (ItemName: 'Четность';   ItemValue: 2)
       );
  begin
    FillComboBox(cboParity, Parities);
  end;

  procedure FillModelID;
  const
    ModelID: array [0..12] of TComboItem =
     (
       (ItemName: 'ВР4149';          ItemValue: 0),
       (ItemName: 'ВР4900';          ItemValue: 1),
       (ItemName: 'Штрих ВТ';      ItemValue: 2),
       (ItemName: 'Штрих АС';        ItemValue: 3),
       (ItemName: 'CAS LP';          ItemValue: 4),
       (ItemName: 'Штрих АС POS';  ItemValue: 5),
       (ItemName: 'Штрих АС мини POS';  ItemValue: 6),
       (ItemName: 'ПетВет серия Е';  ItemValue: 11),
       (ItemName: 'Тензо-М 003/05Д';  ItemValue: 12),
       (ItemName: 'Bolet MD-991';  ItemValue: 13),
       (ItemName: 'Масса-К серии ПВ';  ItemValue: 14),
       (ItemName: 'Масса-К серии ВТ';  ItemValue: 15),
       (ItemName: 'Атол Марта';  ItemValue: 38)
     );
  begin
    FillComboBox(cboModelID, ModelID);
    SendMessage(cboModelID.handle, CB_SETDROPPEDWIDTH , 200, 0);
  end;

begin
  SaveApplicationOnException := Application.OnException;
  Application.OnException := ApplicationException;

  FillPortNumber;
  FillBaudRate;
  FillParity;
  FillModelID;
end;

// DeviceName

procedure TfmDevices.edtDeviceNameExit(Sender: TObject);
begin
  try
    SetDeviceName;
  finally
    UpdateForm;
  end;
end;

// DeviceIndex

procedure TfmDevices.DeviceIndexClick(Sender: TObject);
begin
  try
    SetDeviceIndex;
  finally
    UpdateForm;
  end;
end;

// DeviceNumber

procedure TfmDevices.DeviceNumberClick(Sender: TObject);
begin
  try
    SetDeviceNumber;
  finally
    UpdateForm;
  end;
end;

procedure TfmDevices.btnAddClick(Sender: TObject);
begin
  try
    fScale.AddDevice;
  finally
    UpdateForm;
  end;
end;

procedure TfmDevices.btnDeleteClick(Sender: TObject);
begin
  try
    fScale.DeleteDevice;
  finally
    UpdateForm;
  end;
end;

// PortNumber

procedure TfmDevices.cboPortNumberChange(Sender: TObject);
begin
  try
    SetComboPortNumber;
  finally
    UpdateForm;
  end;
end;

procedure TfmDevices.edtPortNumberExit(Sender: TObject);
begin
  try
    SetEditPortNumber;
  finally
    UpdateForm;
  end;
end;

procedure TfmDevices.udPortNumberClick(Sender: TObject;
  Button: TUDBtnType);
begin
  try
    SetUDPortNumber;
  finally
    UpdateForm;
  end;
end;

// BaudRate

procedure TfmDevices.cboBaudRateChange(Sender: TObject);
begin
  try
    SetComboBaudRate;
  finally
    UpdateForm;
  end;
end;

procedure TfmDevices.edtBaudRateExit(Sender: TObject);
begin
  try
    SetEditBaudRate;
  finally
    UpdateForm;
  end;
end;

procedure TfmDevices.udBaudRateClick(Sender: TObject; Button: TUDBtnType);
begin
  try
    SetUDBaudRate;
  finally
    UpdateForm;
  end;
end;

// Parity

procedure TfmDevices.cboParityChange(Sender: TObject);
begin
  try
    SetComboParity;
  finally
    UpdateForm;
  end;
end;

procedure TfmDevices.edtParityExit(Sender: TObject);
begin
  try
    SetEditParity;
  finally
    UpdateForm;
  end;
end;

procedure TfmDevices.udParityClick(Sender: TObject; Button: TUDBtnType);
begin
  try
    SetUDParity;
  finally
    UpdateForm;
  end;
end;

// ModelID

procedure TfmDevices.cboModelIDChange(Sender: TObject);
begin
  try
    SetComboModelID;
  finally
    UpdateForm;
  end;
end;

procedure TfmDevices.edtModelIDExit(Sender: TObject);
begin
  try
    SetEditModelID;
  finally
    UpdateForm;
  end;
end;

procedure TfmDevices.udModelIDClick(Sender: TObject; Button: TUDBtnType);
begin
  try
    SetUDModelID;
  finally
    UpdateForm;
  end;
end;

// DeviceEnabled

procedure TfmDevices.chkDeviceEnabledClick(Sender: TObject);
begin
  try
    SetDeviceEnabled;
  finally
    UpdateForm;
  end;
end;

{ Обработчики событий }

procedure TfmDevices.ApplicationException(Sender: TObject; E: Exception);
begin
  if E is EScaleError then
  begin
    edtResultCode.Text := IntToStr(EScaleError(E).ErrorCode);
  end;
  edtResultDescription.Text := E.Message;
end;

procedure TfmDevices.FormDestroy(Sender: TObject);
begin
  Application.OnException := SaveApplicationOnException;
end;

procedure TfmDevices.btnCloseClick(Sender: TObject);
begin
  Close;
end;

end.
