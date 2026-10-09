@echo off
setlocal EnableDelayedExpansion
chcp 65001 >nul 2>&1

REM ============================================
REM  Store System - Database Initialization
REM  Called automatically on first run
REM ============================================

set "MYSQL_PORT=%1"
if "%MYSQL_PORT%"=="" set "MYSQL_PORT=3306"

set "ROOT_DIR=%~dp0.."
pushd "%ROOT_DIR%"
set "ROOT_DIR=%CD%"
popd

set "MYSQL_DIR=%ROOT_DIR%\mysql"
set "SQL_FILE=%ROOT_DIR%\htdocs\Store-System\installer_build\store_system.sql"

echo.
echo ==================================================
echo   Database Initialization
echo ==================================================
echo.

REM Check if SQL file exists
if not exist "%SQL_FILE%" (
    echo [ERROR] SQL file not found: %SQL_FILE%
    echo Please make sure the installation is complete.
    pause
    exit /b 1
)

echo [....] Creating database...
"%MYSQL_DIR%\bin\mysql.exe" --host=127.0.0.1 --port=%MYSQL_PORT% -u root --default-character-set=utf8mb4 -e "CREATE DATABASE IF NOT EXISTS store_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

if errorlevel 1 (
    echo [ERROR] Failed to create database!
    pause
    exit /b 1
)
echo [OK] Database created

echo [....] Importing tables and data...
"%MYSQL_DIR%\bin\mysql.exe" --host=127.0.0.1 --port=%MYSQL_PORT% -u root --default-character-set=utf8mb4 store_management < "%SQL_FILE%"

if errorlevel 1 (
    echo [ERROR] Failed to import data!
    pause
    exit /b 1
)
echo [OK] Tables and data imported successfully

echo.
echo [OK] Database initialization complete!
echo.
