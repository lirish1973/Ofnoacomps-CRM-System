@echo off
title Ofnoacomps CRM

cd /d "C:\Users\ofnoa\ofnoacomps-crm"

echo Starting CRM Backend (port 3001)...
start "CRM Backend" cmd /k "cd /d C:\Users\ofnoa\ofnoacomps-crm && node server/index.js"

timeout /t 2 /nobreak >nul

echo Starting CRM Frontend (port 5173)...
start "CRM Frontend" cmd /k "cd /d C:\Users\ofnoa\ofnoacomps-crm && node_modules\.bin\vite.cmd"

timeout /t 3 /nobreak >nul

echo Opening browser...
start "" "http://localhost:5173"

echo Done!
