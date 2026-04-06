@echo off
title Ofnoacomps CRM System
echo.
echo  ==========================================
echo   Ofnoacomps CRM System - Starting...
echo  ==========================================
echo.
echo  [1/2] Starting backend server (port 3001)...
echo  [2/2] Starting frontend (port 5173)...
echo.
echo  When ready, the browser will open automatically.
echo  To stop: Close this window.
echo.
cd /d C:\Users\ofnoa\ofnoacomps-crm
npm run dev
