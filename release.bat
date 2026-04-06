@echo off
echo.
echo  =============================================
echo   Ofnoacomps Plugin Release Tool
echo  =============================================
echo.
echo  Available plugins:
echo    ofnoacomps-crm
echo    smart-cart-recovery
echo.
set /p PLUGIN=Plugin name: 
set /p VERSION=New version (e.g. 1.0.2): 
echo.
echo  Running release for %PLUGIN% v%VERSION% ...
echo.
C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe -ExecutionPolicy Bypass -File "%~dp0release-plugin.ps1" -Plugin "%PLUGIN%" -Version "%VERSION%"
echo.
pause
