@echo off
title NightLifeBD - Local Server Launcher
echo ===================================================
echo     NightLifeBD - AI Safety Intelligence System
echo                 1-Click Launcher
echo ===================================================
echo.

cd /d "%~dp0"
if exist "nightlifebd-main" cd nightlifebd-main
if exist "nightlifebd\" cd nightlifebd
if exist "nightlifebd\" cd nightlifebd

:: Check if PHP is available
where php >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo [ERROR] PHP is not found in your system PATH!
    echo Please install PHP or XAMPP and add PHP to your environment variables.
    pause
    exit /b 1
)

:: Ensure .env exists
if not exist ".env" (
    echo [INFO] Creating .env file from .env.example...
    copy .env.example .env >nul
    call php artisan key:generate --ansi
)

:: Check if MySQL is running on port 3306
netstat -ano | findstr :3306 >nul
if %ERRORLEVEL% equ 0 (
    echo [OK] MySQL is active on port 3306.
    echo [INFO] Ensuring MySQL database 'nightlifebd' exists...
    php -r "try { $p=new PDO('mysql:host=127.0.0.1;port=3306','root',''); $p->exec('CREATE DATABASE IF NOT EXISTS nightlifebd CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'); } catch (Exception $e) {}"
    php artisan migrate --force >nul 2>&1
) else (
    echo [INFO] MySQL is not running on port 3306.
    if exist "C:\xampp\mysql\bin\mysqld.exe" (
        echo [INFO] Attempting to start MySQL from XAMPP...
        start "" "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone
        timeout /t 3 /nobreak >nul
        php -r "try { $p=new PDO('mysql:host=127.0.0.1;port=3306','root',''); $p->exec('CREATE DATABASE IF NOT EXISTS nightlifebd CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'); } catch (Exception $e) {}"
        php artisan migrate --force >nul 2>&1
    ) else (
        echo [INFO] MySQL not detected. Falling back to pre-seeded embedded SQLite database...
        php -r "file_put_contents('.env', preg_replace(['/DB_CONNECTION=mysql/', '/DB_DATABASE=.*/'], ['DB_CONNECTION=sqlite', 'DB_DATABASE=database/database.sqlite'], file_get_contents('.env')));"
    )
)

:: Create storage link if missing
if not exist "public\storage" (
    php artisan storage:link >nul 2>&1
)

echo [OK] Environment is ready.
echo [INFO] Launching NightLifeBD in your browser at http://127.0.0.1:8000 ...
start "" http://127.0.0.1:8000

echo.
echo ===================================================
echo  NightLifeBD Server is RUNNING at: http://127.0.0.1:8000
echo  Press Ctrl+C to stop the server
echo ===================================================
echo.
php artisan serve --host=127.0.0.1 --port=8000
pause

