@echo off
setlocal EnableDelayedExpansion
chcp 65001 >nul 2>&1

REM ============================================
REM  Store System - Stop Script
REM ============================================

set "ROOT_DIR=%~dp0.."
pushd "%ROOT_DIR%"
set "ROOT_DIR=%CD%"
popd

set "MYSQL_DIR=%ROOT_DIR%\mysql"
set "CONFIG_DIR=%ROOT_DIR%\config"
set "PORTS_FILE=%CONFIG_DIR%\ports.ini"

set "MYSQL_PORT=3306"
if exist "%PORTS_FILE%" (
    for /f "tokens=1,2 delims==" %%a in ('type "%PORTS_FILE%"') do (
        if /i "%%a"=="mysql_port" set "MYSQL_PORT=%%b"
    )
)

echo [....] Stopping MySQL...
"%MYSQL_DIR%\bin\mysqladmin.exe" --host=127.0.0.1 --port=%MYSQL_PORT% -u root shutdown >nul 2>&1
timeout /t 1 /nobreak >nul 2>&1

echo [....] Stopping Apache and StoreSystem processes...
powershell -NoProfile -Command "$root = '%ROOT_DIR%'.Replace('\', '\\'); Get-Process -Name httpd, mysqld -ErrorAction SilentlyContinue | Where-Object { $_.Path -like ($root + '*') } | Stop-Process -Force" >nul 2>&1

echo [OK] All Store System services stopped cleanly.
exit /b 0