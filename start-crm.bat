@echo off
title Ofnoacomps CRM System
echo.
echo  ==========================================
echo   Ofnoacomps CRM System - Starting...
echo  ==========================================
echo.
echo  Starting backend server (port 3001)...
cd /d C:\Users\ofnoa\ofnoacomps-crm
start "CRM Backend" cmd /k "node server/index.js"
timeout /t 2 /nobreak > nul

echo  Starting frontend (port 5173)...
start "CRM Frontend" cmd /k "node_modules\.bin\vite"
timeout /t 3 /nobreak > nul

echo  Opening browser...
start "" "http://localhost:5173"
echo.
echo  Done! Close the two server windows to stop CRM.
