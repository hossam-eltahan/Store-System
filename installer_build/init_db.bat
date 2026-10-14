@echo off
echo =======================================
echo Initializing Database for Store System
echo =======================================
cd /d "%~dp0\..\..\.."

set "MYSQL_PORT=%1"
if "%MYSQL_PORT%"=="" set "MYSQL_PORT=3307"

echo Starting MySQL server on port %MYSQL_PORT%...
start /B "" "mysql\bin\mysqld.exe" --defaults-file=mysql\bin\my.ini --standalone

echo Waiting for MySQL to initialize (10 seconds)...
timeout /t 10 /nobreak >nul

echo Creating Database and Importing Tables...
mysql\bin\mysql.exe --host=127.0.0.1 --port=%MYSQL_PORT% -u root --default-character-set=utf8mb4 -e "CREATE DATABASE IF NOT EXISTS cafe_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql\bin\mysql.exe --host=127.0.0.1 --port=%MYSQL_PORT% -u root --default-character-set=utf8mb4 cafe_management < "htdocs\Store-System\installer_build\fresh_install.sql"

echo Import complete. Shutting down MySQL...
mysql\bin\mysqladmin.exe --host=127.0.0.1 --port=%MYSQL_PORT% -u root shutdown

echo Initialization Finished!
