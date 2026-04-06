$ws = New-Object -ComObject WScript.Shell
$s = $ws.CreateShortcut("C:\Users\ofnoa\Desktop\Ofnoacomps CRM.lnk")
$s.TargetPath = "C:\Users\ofnoa\ofnoacomps-crm\start-crm.bat"
$s.WorkingDirectory = "C:\Users\ofnoa\ofnoacomps-crm"
$s.Description = "Ofnoacomps CRM System"
$s.IconLocation = "C:\Windows\System32\SHELL32.dll,162"
$s.Save()
Write-Host "Shortcut created on Desktop!"
