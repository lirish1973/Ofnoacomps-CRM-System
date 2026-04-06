@echo off
chcp 65001 >nul
echo.
echo  ╔══════════════════════════════════════╗
echo  ║      Ofnoacomps Plugin Release       ║
echo  ╚══════════════════════════════════════╝
echo.
echo  פלאגינים זמינים:
echo    1. hoco-crm
echo    2. smart-cart-recovery
echo.
set /p PLUGIN=" בחר שם פלאגין: "
set /p VERSION=" גרסה חדשה (לדוגמה: 1.0.1): "
echo.
echo  מריץ release עבור %PLUGIN% v%VERSION%...
echo.
powershell.exe -ExecutionPolicy Bypass -File "%~dp0release-plugin.ps1" -Plugin "%PLUGIN%" -Version "%VERSION%"
echo.
pause
