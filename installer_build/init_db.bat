@echo off
echo =======================================
echo Initializing Database for Store System
echo =======================================
cd /d "%~dp0\..\..\.."

echo Starting MySQL server...
start /B "" "mysql\bin\mysqld.exe" --defaults-file=mysql\bin\my.ini --standalone

echo Waiting for MySQL to initialize (10 seconds)...
timeout /t 10 /nobreak >nul

echo Creating Database and Importing Tables...
mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 -e "CREATE DATABASE IF NOT EXISTS store_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 store_management < "htdocs\StoreSystem\installer_build\store_system.sql"

echo Import complete. Shutting down MySQL...
mysql\bin\mysqladmin.exe -u root shutdown

echo Initialization Finished!
