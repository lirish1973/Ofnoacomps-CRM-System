@echo off
REM הפעלת Server Elementor Leads

cd /d C:\Users\ofnoa\ofnoacomps-crm\server

echo.
echo 🚀 הפעלת Elementor Leads Server
echo ================================
echo.

REM הגדר Email Password (אם קיים)
if not defined EMAIL_PASS (
    echo ⚠️ דרוש: EMAIL_PASS environment variable
    echo.
    echo הגדרה:
    echo $env:EMAIL_PASS = "xxxx xxxx xxxx xxxx"
    echo.
)

REM הפעל את השרת
"C:\Program Files\nodejs\node.exe" index.js

REM אם הודר, הציע הוראות
if errorlevel 1 (
    echo.
    echo ❌ שגיאה בהפעלת השרת
    echo.
    pause
)
