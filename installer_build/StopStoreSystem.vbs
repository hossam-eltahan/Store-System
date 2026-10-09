Set WshShell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")
rootDir = fso.GetParentFolderName(WScript.ScriptFullName)
stopBat = rootDir & "\scripts\stop.bat"

If fso.FileExists(stopBat) Then
    WshShell.Run chr(34) & stopBat & chr(34), 0, True
    MsgBox "تم إيقاف تشغيل النظام والخدمات بنجاح.", 64, "Store System"
End If