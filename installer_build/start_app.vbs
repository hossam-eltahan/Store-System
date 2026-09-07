Set WshShell = CreateObject("WScript.Shell")

' Get the folder containing this script
scriptPath = WScript.ScriptFullName
Set fso = CreateObject("Scripting.FileSystemObject")
scriptDir = fso.GetParentFolderName(scriptPath)

' Calculate XAMPP root folder (3 levels up: installer_build -> StoreSystem -> htdocs -> XAMPP Root)
xamppRoot = fso.GetParentFolderName(fso.GetParentFolderName(fso.GetParentFolderName(scriptDir)))

' Set working directory to XAMPP root
WshShell.CurrentDirectory = xamppRoot

' Start Apache silently
WshShell.Run chr(34) & "apache_start.bat" & chr(34), 0, false
' Start MySQL silently
WshShell.Run chr(34) & "mysql_start.bat" & chr(34), 0, false
' Wait 3 seconds for servers to start
WScript.Sleep 3000
' Open the default browser to the system URL
WshShell.Run "http://127.0.0.1:8085/StoreSystem"
