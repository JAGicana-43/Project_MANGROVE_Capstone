' Launches queue-worker.ps1 via wscript.exe's Run method with window style 0
' (truly hidden), instead of Task Scheduler invoking powershell.exe directly
' with -WindowStyle Hidden.
'
' Why this file exists: -WindowStyle Hidden on the powershell.exe action
' itself is well-documented as unreliable specifically when the process is
' launched BY Task Scheduler (as opposed to run directly by a user) -- it
' can still flash a console window on screen briefly before the hidden
' style takes effect. Confirmed directly as the actual cause here: the
' scheduled task's last run time lined up exactly with a real reported
' "PowerShell keeps popping up" complaint, once every 5 minutes matching
' this task's repeat trigger. WScript.Shell.Run's window-style parameter
' (0 = hidden) does not have this flash problem.
Set objShell = CreateObject("WScript.Shell")
objShell.Run "powershell.exe -NoProfile -ExecutionPolicy Bypass -File ""D:\CAPSTONE\Project_Mangrove_System\scripts\queue-worker.ps1""", 0, False
