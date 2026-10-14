@echo off
echo =======================================
echo Initializing Database for Store System
echo =======================================
cd /d "%~dp0\..\..\.."
REM When installed, scripts live at {app}\scripts so ROOT is passed explicitly
set "ROOT_DIR=%~2"
if not "%ROOT_DIR%"=="" cd /d "%ROOT_DIR%"

set "MYSQL_PORT=%1"
if "%MYSQL_PORT%"=="" set "MYSQL_PORT=3307"

echo Starting MySQL server on port %MYSQL_PORT%...
start /B "" "mysql\bin\mysqld.exe" --defaults-file=mysql\bin\my.ini --standalone

echo Waiting for MySQL to initialize...
ping -n 11 127.0.0.1 >nul

echo Creating Database and Importing Tables...
mysql\bin\mysql.exe --host=127.0.0.1 --port=%MYSQL_PORT% -u root --default-character-set=utf8mb4 -e "CREATE DATABASE IF NOT EXISTS cafe_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql\bin\mysql.exe --host=127.0.0.1 --port=%MYSQL_PORT% -u root --default-character-set=utf8mb4 cafe_management < "htdocs\Store-System\installer_build\fresh_install.sql"

echo Import complete. MySQL left running for the app.
echo Initialization Finished!
