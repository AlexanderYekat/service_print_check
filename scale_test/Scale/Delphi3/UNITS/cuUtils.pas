unit cuUtils;

interface

Uses
  // VCL
  Windows, Registry, Graphics, SysUtils, Classes;

const
  REGSTR_KEY_FONT             = 'Font';
  REGSTR_PATH_FONT            = 'Software\ATOL\';
  REGSTR_VAL_FONTNAME         = 'FontName';
  REGSTR_VAL_FONTSIZE         = 'FontSize';
  REGSTR_VAL_FONTCOLOR        = 'FontColor';
  REGSTR_VAL_FONTCHARSET      = 'FontCharSet';
  REGSTR_VAL_FONTISBOLD       = 'FontIsBold';
  REGSTR_VAL_FONTISITALIC     = 'FontIsItalic';
  REGSTR_VAL_FONTISUNDERLINED = 'FontIsUnderlined';
  REGSTR_VAL_FONTISSTRIKEDOUT = 'FontIsStriledOut';

  DEFAULT_FONTNAME            = 'MS Sans Serif';
  DEFAULT_FONTSIZE            = 8;
  DEFAULT_FONTCOLOR           = clBlack;
  DEFAULT_FONTCHARSET         = DEFAULT_CHARSET;
  DEFAULT_FONTISBOLD          = False;
  DEFAULT_FONTISITALIC        = False;
  DEFAULT_FONTISUNDERLINED    = False;
  DEFAULT_FONTISSTRIKEDOUT    = False;

procedure RegReadFont(Font :TFont);
procedure RegSaveFont(Font: TFont);
function GetFileVersionInfo: string;
procedure RegDeleteKeyEx(const KeyName: string);

implementation

procedure RegReadFont(Font :TFont);
var
  Reg: TRegistry;
begin
  Reg := TRegistry.Create;
  try
    Reg.RootKey := HKEY_CURRENT_USER;
    if Reg.OpenKey(REGSTR_PATH_FONT + REGSTR_KEY_FONT, False) then
    begin
      if Reg.ValueExists(REGSTR_VAL_FONTNAME) then
        Font.Name := Reg.ReadString(REGSTR_VAL_FONTNAME)
      else
        Font.Name := DEFAULT_FONTNAME;

      if Reg.ValueExists(REGSTR_VAL_FONTSIZE) then
        Font.Size := Reg.ReadInteger(REGSTR_VAL_FONTSIZE)
      else
        Font.Size := DEFAULT_FONTSIZE;


      if Reg.ValueExists(REGSTR_VAL_FONTCOLOR) then
        Font.Color := Reg.ReadInteger(REGSTR_VAL_FONTCOLOR)
      else
        Font.Color := DEFAULT_FONTCOLOR;

      if Reg.ValueExists(REGSTR_VAL_FONTCHARSET) then
        Font.Charset := Reg.ReadInteger(REGSTR_VAL_FONTCHARSET)
      else
        Font.Charset := DEFAULT_FONTCHARSET;

      Font.Style :=[];

      if Reg.ValueExists(REGSTR_VAL_FONTISBOLD) and
        Reg.ReadBool(REGSTR_VAL_FONTISBOLD) then
        Font.Style := Font.Style + [fsBold];

      if Reg.ValueExists(REGSTR_VAL_FONTISITALIC) and
        Reg.ReadBool(REGSTR_VAL_FONTISITALIC) then
        Font.Style := Font.Style + [fsItalic];

      if Reg.ValueExists(REGSTR_VAL_FONTISUNDERLINED) and
        Reg.ReadBool(REGSTR_VAL_FONTISUNDERLINED) then
        Font.Style := Font.Style + [fsUnderline];

      if Reg.ValueExists(REGSTR_VAL_FONTISSTRIKEDOUT) and
        Reg.ReadBool(REGSTR_VAL_FONTISSTRIKEDOUT) then
        Font.Style := Font.Style + [fsStrikeOut];
    end else
    begin
      Font.Name := DEFAULT_FONTNAME;
      Font.Size := DEFAULT_FONTSIZE;
      Font.Color := DEFAULT_FONTCOLOR;
      Font.Charset := DEFAULT_FONTCHARSET;
      Font.Style :=[];
    end;
  finally
    Reg.Free;
  end;
end;

procedure RegSaveFont(Font: TFont);
var
  Reg: TRegistry;
begin
  Reg := TRegistry.create;
  try
    Reg.RootKey := HKEY_CURRENT_USER;
    Reg.OpenKey(REGSTR_PATH_FONT + REGSTR_KEY_FONT, True);
    Reg.WriteString(REGSTR_VAL_FONTNAME, Font.Name);
    Reg.WriteInteger(REGSTR_VAL_FONTSIZE, Font.Size);
    Reg.WriteInteger(REGSTR_VAL_FONTCOLOR, Font.Color);
    Reg.WriteInteger(REGSTR_VAL_FONTCHARSET, Font.Charset);
    Reg.WriteBool(REGSTR_VAL_FONTISBOLD, (fsBold in Font.Style));
    Reg.WriteBool(REGSTR_VAL_FONTISITALIC, (fsItalic in Font.Style));
    Reg.WriteBool(REGSTR_VAL_FONTISUNDERLINED, (fsUnderline in Font.Style));
    Reg.WriteBool(REGSTR_VAL_FONTISSTRIKEDOUT, (fsStrikeOut in Font.Style));
  finally
    Reg.Free;
  end;
end;

function GetFileVersionInfo: string;
var
  hVerInfo: THandle;
  hGlobal: THandle;
  AddrRes: pointer;
  Buf: array[0..7]of byte;

  MajorVersion: WORD;
  MinorVersion: WORD;
  ProductRelease: WORD;
  ProductBuild: WORD;
begin
  hVerInfo:= FindResource(hInstance, '#1', RT_VERSION);
  if hVerInfo = 0 then
    Result := '0.0.0.0'
  else
  begin
    hGlobal := LoadResource(hInstance, hVerInfo);
    if hGlobal = 0 then
      Result := '0.0.0.0'
    else
    begin
      AddrRes:= LockResource(hGlobal);
      CopyMemory(@Buf, Pointer(Integer(AddrRes)+48), 8);

      MinorVersion := Buf[0] + Buf[1]*$100;
      MajorVersion := Buf[2] + Buf[3]*$100;
      ProductBuild := Buf[4] + Buf[5]*$100;
      ProductRelease := Buf[6] + Buf[7]*$100;

      Result := Format('%d.%d.%d.%d',
        [MajorVersion, MinorVersion, ProductRelease, ProductBuild]);
      FreeResource(hGlobal);
    end;
  end;
end;

procedure RegDeleteKeyEx(const KeyName: string);
var
  i: Integer;
  Reg: TRegistry;
  Strings: TStrings;
begin
  Reg := TRegistry.Create;
  Reg.RootKey := HKEY_CURRENT_USER;
  Strings := TStringList.Create;
  try
    if Reg.OpenKey(KeyName, False) then
    begin
      Reg.GetKeyNames(Strings);
      for i := 0 to Strings.Count-1 do
      begin
        RegDeleteKeyEx(KeyName + '\' + Strings[i]);
      end;
      Reg.CloseKey;
      Reg.DeleteKey(KeyName);
    end;
  finally
    Reg.Free;
    Strings.Free;
  end;
end;

end.
