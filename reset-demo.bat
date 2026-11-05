@echo off
title NightLifeBD - Reset Demo Data
echo ===================================================
echo     NightLifeBD - Reset Demo State
echo ===================================================
echo.
cd /d "%~dp0"
php artisan demo:reset --yes
echo.
echo Demo reset complete!
pause

