; Inno Setup script for TDS School Management Software (built by .github/workflows/build-windows.yml)
#ifndef AppVersion
  #define AppVersion "0.0.0"
#endif
#ifndef BuildDir
  #define BuildDir "..\build"
#endif
#ifndef OutputDir
  #define OutputDir "..\dist"
#endif

[Setup]
AppId={{0895A332-66C5-4904-B488-2435D3589683}
AppName=TDS School Management Software
AppVersion={#AppVersion}
AppVerName=TDS School Management Software {#AppVersion}
AppPublisher=TDS School Management Software
DefaultDirName={localappdata}\Programs\TDS School Management
DisableProgramGroupPage=yes
DisableDirPage=auto
PrivilegesRequired=lowest
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
OutputDir={#OutputDir}
OutputBaseFilename=TDS-School-Setup
SetupIconFile=app.ico
UninstallDisplayIcon={app}\app.ico
UninstallDisplayName=TDS School Management Software
Compression=lzma2/max
SolidCompression=yes
WizardStyle=modern
CloseApplications=no

[Tasks]
Name: "desktopicon"; Description: "Create a desktop shortcut"; GroupDescription: "Shortcuts:"

[InstallDelete]
; remove old program files on upgrade (school data is elsewhere and is never deleted)
Type: filesandordirs; Name: "{app}\app\vendor"
Type: filesandordirs; Name: "{app}\app\bootstrap\cache\*.php"

[Files]
Source: "{#BuildDir}\*"; DestDir: "{app}"; Flags: recursesubdirs createallsubdirs ignoreversion

[Icons]
Name: "{userprograms}\TDS School Management Software"; Filename: "{sys}\wscript.exe"; Parameters: """{app}\launch.vbs"""; WorkingDir: "{app}"; IconFilename: "{app}\app.ico"
Name: "{userprograms}\Stop TDS School Management"; Filename: "{sys}\wscript.exe"; Parameters: """{app}\stop.vbs"""; WorkingDir: "{app}"; IconFilename: "{app}\app.ico"
Name: "{userdesktop}\TDS School Management Software"; Filename: "{sys}\wscript.exe"; Parameters: """{app}\launch.vbs"""; WorkingDir: "{app}"; IconFilename: "{app}\app.ico"; Tasks: desktopicon

[Run]
Filename: "{sys}\wscript.exe"; Parameters: """{app}\launch.vbs"""; Description: "Open TDS School Management Software now"; Flags: postinstall nowait skipifsilent

[UninstallRun]
Filename: "powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\stop.ps1"""; Flags: runhidden; RunOnceId: "StopServer"

[Messages]
FinishedLabel=TDS School Management Software is installed.%n%nYour school data is saved on this computer in your user AppData\Roaming\TDS School Data folder. Back up that folder regularly.

[Code]
function PrepareToInstall(var NeedsRestart: Boolean): String;
var
  ResultCode: Integer;
  StopScript: String;
begin
  Result := '';
  StopScript := ExpandConstant('{app}\stop.ps1');
  if FileExists(StopScript) then
    Exec('powershell.exe', '-NoProfile -ExecutionPolicy Bypass -File "' + StopScript + '"', '', SW_HIDE, ewWaitUntilTerminated, ResultCode);
end;
