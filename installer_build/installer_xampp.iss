; ============================================
; Store System - XAMPP Edition Installer
; Lightweight: needs official XAMPP at C:\xampp
; Ships ONLY the app (no server binaries -> AV friendly)
; ============================================

#define MyAppName "Store System"
#define MyAppVersion "1.0.3"
#define MyAppPublisher "Store System Inc."

[Setup]
AppId={{015B58FA-7918-4030-A51D-3B4F6A567DDF}
AppName={#MyAppName} (XAMPP Edition)
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
DefaultDirName=C:\xampp\htdocs\Store-System
DisableDirPage=yes
DefaultGroupName={#MyAppName}
AllowNoIcons=yes
OutputDir=C:\StoreSystem_Build\output
OutputBaseFilename=StoreSystem_XAMPP_Setup_v{#MyAppVersion}
Compression=lzma2/ultra64
SolidCompression=yes
WizardStyle=modern
PrivilegesRequired=admin
DisableProgramGroupPage=yes

[Languages]
Name: "arabic"; MessagesFile: "compiler:Languages\Arabic.isl"
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "{cm:CreateDesktopIcon}"; GroupDescription: "{cm:AdditionalIcons}"

[Files]
Source: "..\*"; DestDir: "C:\xampp\htdocs\Store-System"; Excludes: ".git\*,.gitignore,tools\*,backups\*.sql,assets\uploads\*,data\updates\*"; Flags: ignoreversion recursesubdirs createallsubdirs

[Dirs]
Name: "C:\xampp\htdocs\Store-System\backups"
Name: "C:\xampp\htdocs\Store-System\assets\uploads\products"
Name: "C:\xampp\htdocs\Store-System\assets\uploads\avatars"
Name: "C:\xampp\htdocs\Store-System\assets\uploads\logo"
Name: "C:\xampp\htdocs\Store-System\data\updates"

[Icons]
Name: "{group}\{#MyAppName}"; Filename: "wscript.exe"; Parameters: """C:\xampp\htdocs\Store-System\StoreSystem_XAMPP.vbs"""; WorkingDir: "C:\xampp\htdocs\Store-System"
Name: "{commondesktop}\{#MyAppName}"; Filename: "wscript.exe"; Parameters: """C:\xampp\htdocs\Store-System\StoreSystem_XAMPP.vbs"""; WorkingDir: "C:\xampp\htdocs\Store-System"; Tasks: desktopicon

[Code]
function InitializeSetup(): Boolean;
begin
  if not DirExists('C:\xampp') or not FileExists('C:\xampp\mysql\bin\mysql.exe') then
  begin
    MsgBox('XAMPP was not found at C:\xampp' + #13#10 + #13#10 +
           'Please install official XAMPP (PHP 8 + MySQL) to C:\xampp first,' + #13#10 +
           'then run this setup again.' + #13#10 + #13#10 +
           'XAMPP download: https://www.apachefriends.org',
           mbError, MB_OK);
    Result := False;
  end
  else
    Result := True;
end;
