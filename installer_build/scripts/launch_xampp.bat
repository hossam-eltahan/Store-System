@echo off
REM Store System launcher (XAMPP edition): ensures services + database, then opens the app
setlocal
chcp 65001 >nul 2>&1

set "XAMPP=C:\xampp"
if not exist "%XAMPP%\mysql\bin\mysql.exe" (
    echo [ERROR] XAMPP was not found at C:\xampp
    echo Please install XAMPP with PHP 8 and MySQL to C:\xampp first, then run this again.
    pause
    exit /b 1
)
if not exist "%XAMPP%\apache\bin\httpd.exe" (
    echo [ERROR] Apache was not found inside C:\xampp
    echo Please install the full XAMPP package, then run this again.
    pause
    exit /b 1
)

set "APP=%XAMPP%\htdocs\Store-System"

REM 1. Make sure MySQL is running (default XAMPP port 3306, root with empty password)
"%XAMPP%\mysql\bin\mysqladmin.exe" --host=127.0.0.1 --port=3306 -u root ping >nul 2>&1
if errorlevel 1 goto start_mysql
goto mysql_up

:start_mysql
echo Starting MySQL...
start "" /B "%XAMPP%\mysql\bin\mysqld.exe" --defaults-file="%XAMPP%\mysql\bin\my.ini" --standalone
call :wait_mysql
if errorlevel 1 (
    echo [ERROR] MySQL did not start. Open XAMPP Control Panel and start MySQL manually.
    pause
    exit /b 1
)

:mysql_up

REM 2. First run: create and seed the database
"%XAMPP%\mysql\bin\mysql.exe" --host=127.0.0.1 --port=3306 -u root -e "SELECT id FROM cafe_management.users LIMIT 1;" >nul 2>&1
if errorlevel 1 (
    echo First run: creating database...
    "%XAMPP%\mysql\bin\mysql.exe" --host=127.0.0.1 --port=3306 -u root -e "CREATE DATABASE IF NOT EXISTS cafe_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    "%XAMPP%\mysql\bin\mysql.exe" --host=127.0.0.1 --port=3306 -u root --default-character-set=utf8mb4 cafe_management < "%APP%\installer_build\fresh_install.sql"
    echo Database ready. Default login: admin / admin123 - change it immediately!
)

REM 3. Make sure Apache is running (default XAMPP port 80)
netstat -ano | findstr /R /C:":80 " | findstr "LISTENING" >nul 2>&1
if errorlevel 1 (
    echo Starting Apache...
    start "" /B "%XAMPP%\apache\bin\httpd.exe"
    timeout /t 2 /nobreak >nul 2>&1
)

REM 4. Open the app
start "" "http://127.0.0.1/Store-System/"
exit /b 0

REM Wait up to ~20s for MySQL to answer ping
:wait_mysql
for /L %%i in (1,1,20) do (
    timeout /t 1 /nobreak >nul 2>&1
    "%XAMPP%\mysql\bin\mysqladmin.exe" --host=127.0.0.1 --port=3306 -u root ping >nul 2>&1
    if not errorlevel 1 exit /b 0
)
exit /b 1
