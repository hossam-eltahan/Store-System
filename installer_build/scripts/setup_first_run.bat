@echo off
setlocal EnableDelayedExpansion
chcp 65001 >nul 2>&1

set "ROOT_DIR=%~dp0.."
pushd "%ROOT_DIR%"
set "ROOT_DIR=%CD%"
popd

set "MYSQL_DIR=%ROOT_DIR%\mysql"
set "DATA_DIR=%ROOT_DIR%\data"

REM Create all required directories
if not exist "%ROOT_DIR%\config" mkdir "%ROOT_DIR%\config"
if not exist "%ROOT_DIR%\data" mkdir "%ROOT_DIR%\data"
if not exist "%ROOT_DIR%\tmp" mkdir "%ROOT_DIR%\tmp"
if not exist "%ROOT_DIR%\logs" mkdir "%ROOT_DIR%\logs"
if not exist "%ROOT_DIR%\htdocs\Store-System\backups" mkdir "%ROOT_DIR%\htdocs\Store-System\backups"
if not exist "%ROOT_DIR%\htdocs\Store-System\assets\uploads\products" mkdir "%ROOT_DIR%\htdocs\Store-System\assets\uploads\products"
if not exist "%ROOT_DIR%\htdocs\Store-System\assets\uploads\avatars" mkdir "%ROOT_DIR%\htdocs\Store-System\assets\uploads\avatars"

REM Copy default MySQL data if data dir is empty
if not exist "%DATA_DIR%\mysql" (
    if exist "%MYSQL_DIR%\data" (
        xcopy "%MYSQL_DIR%\data" "%DATA_DIR%\" /E /I /Q /Y >nul 2>&1
    )
)

REM Grant full permissions to all users on critical directories
REM This is essential so the app can run without admin rights after install
icacls "%ROOT_DIR%\data" /grant "Everyone:(OI)(CI)F" /T /Q >nul 2>&1
icacls "%ROOT_DIR%\tmp" /grant "Everyone:(OI)(CI)F" /T /Q >nul 2>&1
icacls "%ROOT_DIR%\logs" /grant "Everyone:(OI)(CI)F" /T /Q >nul 2>&1
icacls "%ROOT_DIR%\config" /grant "Everyone:(OI)(CI)F" /T /Q >nul 2>&1
icacls "%ROOT_DIR%\htdocs\Store-System\assets\uploads" /grant "Everyone:(OI)(CI)F" /T /Q >nul 2>&1
icacls "%ROOT_DIR%\htdocs\Store-System\backups" /grant "Everyone:(OI)(CI)F" /T /Q >nul 2>&1

exit /b 0