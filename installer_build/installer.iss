; ============================================
; Store System - Inno Setup Installer Script
; ============================================

#define MyAppName "Store System"
#define MyAppNameAr "نظام إدارة المحل"
#define MyAppVersion "1.0.0"
#define MyAppPublisher "Store System Inc."
#define MyAppExeName "StoreSystem.vbs"

[Setup]
AppId={{C82F1629-45BC-4C2E-8E99-47F6A2209123}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
DefaultDirName=C:\StoreSystem
DefaultGroupName={#MyAppName}
AllowNoIcons=yes
OutputDir=output
OutputBaseFilename=StoreSystem_Setup_v{#MyAppVersion}
Compression=lzma2/ultra64
SolidCompression=yes
WizardStyle=modern
PrivilegesRequired=admin
DisableDirPage=no
DisableProgramGroupPage=yes

[Languages]
Name: "arabic"; MessagesFile: "compiler:Languages\Arabic.isl"
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "{cm:CreateDesktopIcon}"; GroupDescription: "{cm:AdditionalIcons}"

[Files]
; Server core files
Source: "portable\apache\*"; DestDir: "{app}\apache"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "portable\mysql\*"; DestDir: "{app}\mysql"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "portable\php\*"; DestDir: "{app}\php"; Flags: ignoreversion recursesubdirs createallsubdirs

; Initial database data directory
Source: "portable\data\*"; DestDir: "{app}\data"; Flags: ignoreversion recursesubdirs createallsubdirs

; Application files
Source: "portable\htdocs\Store-System\*"; DestDir: "{app}\htdocs\Store-System"; Flags: ignoreversion recursesubdirs createallsubdirs

; Scripts and launcher
Source: "portable\scripts\*"; DestDir: "{app}\scripts"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "portable\StoreSystem.vbs"; DestDir: "{app}"; Flags: ignoreversion

; VC++ Runtime
Source: "prerequisites\vc_redist.x64.exe"; DestDir: "{tmp}"; Flags: deleteafterinstall

[Dirs]
Name: "{app}\config"
Name: "{app}\data"
Name: "{app}\tmp"
Name: "{app}\logs"
Name: "{app}\htdocs\Store-System\backups"
Name: "{app}\htdocs\Store-System\assets\uploads\products"
Name: "{app}\htdocs\Store-System\assets\uploads\avatars"

[Icons]
Name: "{group}\{#MyAppName}"; Filename: "wscript.exe"; Parameters: """{app}\{#MyAppExeName}"""; WorkingDir: "{app}"
Name: "{group}\Stop {#MyAppName}"; Filename: "{app}\scripts\stop.bat"; WorkingDir: "{app}"
Name: "{group}\Uninstall {#MyAppName}"; Filename: "{uninstallexe}"
Name: "{commondesktop}\{#MyAppName}"; Filename: "wscript.exe"; Parameters: """{app}\{#MyAppExeName}"""; WorkingDir: "{app}"; Tasks: desktopicon

[Run]
; Install VC++ silently if needed
Filename: "{tmp}\vc_redist.x64.exe"; Parameters: "/install /quiet /norestart"; StatusMsg: "Installing Visual C++ Runtime..."; Flags: waituntilterminated skipifnotsilent

; Run first-time setup
Filename: "{app}\scripts\setup_first_run.bat"; StatusMsg: "Preparing environment..."; Flags: runhidden waituntilterminated

; Launch after finish silently via WScript
Filename: "wscript.exe"; Parameters: """{app}\{#MyAppExeName}"""; Description: "تشغيل النظام الآن"; Flags: nowait postinstall skipifsilent

[UninstallRun]
; Stop all running services before deleting files
Filename: "{app}\scripts\stop.bat"; Flags: runhidden waituntilterminated

[UninstallDelete]
; Delete all created runtime files and directories
Type: filesandordirs; Name: "{app}\data"
Type: filesandordirs; Name: "{app}\logs"
Type: filesandordirs; Name: "{app}\tmp"
Type: filesandordirs; Name: "{app}\config"
Type: filesandordirs; Name: "{app}"

[Code]
function InitializeUninstall(): Boolean;
begin
  Result := MsgBox('هل أنت متأكد من رغبتك في إزالة تثبيت النظام بالكامل وحذف جميع ملفاته؟',
                    mbConfirmation, MB_YESNO) = IDYES;
end;

procedure CurUninstallStepChanged(CurUninstallStep: TUninstallStep);
begin
  if CurUninstallStep = usPostUninstall then
  begin
    Sleep(1000);
    DelTree(ExpandConstant('{app}'), True, True, True);
  end;
end;