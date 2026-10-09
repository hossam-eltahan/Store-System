Set WshShell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")
rootDir = fso.GetParentFolderName(WScript.ScriptFullName)
startBat = rootDir & "\scripts\start.bat"

If Not fso.FileExists(startBat) Then
    MsgBox "Error: scripts\start.bat not found in " & rootDir, 16, "Store System"
    WScript.Quit 1
End If

WshShell.Run chr(34) & startBat & chr(34), 0, False