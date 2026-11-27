' Stops the local server without showing a console window
Set fso = CreateObject("Scripting.FileSystemObject")
dir = fso.GetParentFolderName(WScript.ScriptFullName)
CreateObject("WScript.Shell").Run "powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File """ & dir & "\stop.ps1""", 0, True
MsgBox "TDS School Management Software has been stopped. Your data is saved.", 64, "TDS School Management Software"
