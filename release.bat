@echo off
chcp 65001 >nul

:: ???? git ?-PATH ?? ?? ????
where git >nul 2>&1
if errorlevel 1 (
    set "PATH=%PATH%;C:\Program Files\Git\bin;C:\Program Files\Git\cmd"
)

:: ???? ??????? ??????? (?? ?? ????? ?????? ??? ????)
cd /d "%~dp0"

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
set /p VERSION=New version (e.g. 1.3.3): 
echo.
echo  Running release for %PLUGIN% v%VERSION% ...
echo.

C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe ^
    -ExecutionPolicy Bypass ^
    -File "%~dp0release-plugin.ps1" ^
    -Plugin "%PLUGIN%" ^
    -Version "%VERSION%"

echo.
if errorlevel 1 (
    echo  [ERROR] Release failed! See output above.
) else (
    echo  [OK] Release complete!
)
echo.
pause
