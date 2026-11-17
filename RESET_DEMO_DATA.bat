@echo off
title NightLifeBD - Reset Demo Data
echo ===================================================
echo     NightLifeBD - Reset Demo State
echo ===================================================
echo.
cd /d "%~dp0"
if exist "nightlifebd-main" cd nightlifebd-main
if exist "nightlifebd\" cd nightlifebd
if exist "nightlifebd\" cd nightlifebd
php artisan demo:reset --yes
echo.
echo Demo reset complete!
pause

