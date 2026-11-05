@echo off
title NightLifeBD - Local Server
echo ===================================================
echo     NightLifeBD - AI Safety Intelligence System
echo ===================================================
echo.

:: Check if MySQL is running
netstat -ano | findstr :3306 >nul
if %ERRORLEVEL% equ 0 (
    echo [OK] MySQL is already running on port 3306.
) else (
    echo [INFO] Starting MySQL from XAMPP...
    if exist "C:\xampp\mysql\bin\mysqld.exe" (
        start "" "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone
        timeout /t 3 /nobreak >nul
        echo [OK] MySQL started.
    ) else (
        echo [WARN] XAMPP MySQL not found at C:\xampp\mysql. Please start MySQL manually.
    )
)

:: Navigate to project directory
cd /d "%~dp0"

:: Open browser
echo [INFO] Opening http://127.0.0.1:8000 in your browser...
start "" http://127.0.0.1:8000

:: Run Laravel Server
echo.
echo ===================================================
echo  Server is running at: http://127.0.0.1:8000
echo  Press Ctrl+C to stop the server
echo ===================================================
echo.
php artisan serve --host=127.0.0.1 --port=8000
pause

