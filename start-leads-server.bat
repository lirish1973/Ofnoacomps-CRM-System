@echo off
REM הפעלת Leads Server
REM ===============================================

echo.
echo 🚀 Elementor Leads Server
echo ===============================================
echo.

REM Check if node is installed
where node >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo ❌ Node.js is not installed or not in PATH
    echo.
    echo תיקון:
    echo 1. התקן Node.js מ: https://nodejs.org/
    echo 2. לאחר התקנה, אתחל את Terminal
    echo 3. הרץ שוב את הקובץ הזה
    echo.
    pause
    exit /b 1
)

REM Check if nodemailer is installed
cd "%~dp0"
if not exist "node_modules\nodemailer" (
    echo 📦 התקנת תלויות...
    call npm install
)

REM Set email password (optional)
if not defined EMAIL_PASS (
    echo.
    echo 📧 מתבקש: הגדר את סיסמת Gmail App
    echo.
    echo לצורך כך:
    echo 1. עבור ל: https://myaccount.google.com/apppasswords
    echo 2. בחר Mail ו-Windows Computer
    echo 3. העתק את הסיסמה ב-16 ספרות
    echo.
    set /p EMAIL_PASS="הדבק את הסיסמה ושלח Enter: "
)

REM Start server
echo.
echo 🔥 הפעלת השרת...
echo.
cd server
node index.js

pause
