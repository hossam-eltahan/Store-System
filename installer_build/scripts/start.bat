@echo off
setlocal EnableDelayedExpansion
chcp 65001 >nul 2>&1

set "ROOT_DIR=%~dp0.."
pushd "%ROOT_DIR%"
set "ROOT_DIR=%CD%"
popd

set "APACHE_DIR=%ROOT_DIR%\apache"
set "MYSQL_DIR=%ROOT_DIR%\mysql"
set "PHP_DIR=%ROOT_DIR%\php"
set "SCRIPTS_DIR=%ROOT_DIR%\scripts"
set "CONFIG_DIR=%ROOT_DIR%\config"
set "PORTS_FILE=%CONFIG_DIR%\ports.ini"
set "ROOT_FWD=%ROOT_DIR:\=/%"

REM ====================================================
REM 1. Read existing saved ports
REM ====================================================
set "SAVED_APACHE_PORT="
set "SAVED_MYSQL_PORT="

if exist "%PORTS_FILE%" (
    for /f "tokens=1,2 delims==" %%a in ('type "%PORTS_FILE%"') do (
        if /i "%%a"=="apache_port" set "SAVED_APACHE_PORT=%%b"
        if /i "%%a"=="mysql_port" set "SAVED_MYSQL_PORT=%%b"
    )
)

REM ====================================================
REM 2. Check if services are ALREADY running
REM ====================================================
if defined SAVED_MYSQL_PORT if defined SAVED_APACHE_PORT (
    "%MYSQL_DIR%\bin\mysqladmin.exe" --host=127.0.0.1 --port=!SAVED_MYSQL_PORT! -u root ping >nul 2>&1
    if not errorlevel 1 (
        netstat -ano 2>nul | findstr /R /C:":!SAVED_APACHE_PORT! " | findstr "LISTENING" >nul 2>&1
        if not errorlevel 1 (
            REM Both services are already alive and healthy!
            if "!SAVED_APACHE_PORT!"=="80" (
                start "" "http://127.0.0.1/Store-System/"
            ) else (
                start "" "http://127.0.0.1:!SAVED_APACHE_PORT!/Store-System/"
            )
            exit /b 0
        )
    )
)

REM ====================================================
REM 3. Determine MySQL Port
REM ====================================================
set "MYSQL_PORT="

if defined SAVED_MYSQL_PORT (
    "%MYSQL_DIR%\bin\mysqladmin.exe" --host=127.0.0.1 --port=!SAVED_MYSQL_PORT! -u root ping >nul 2>&1
    if not errorlevel 1 (
        set "MYSQL_PORT=!SAVED_MYSQL_PORT!"
    ) else (
        netstat -ano 2>nul | findstr /R /C:":!SAVED_MYSQL_PORT! " | findstr "LISTENING" >nul 2>&1
        if errorlevel 1 (
            set "MYSQL_PORT=!SAVED_MYSQL_PORT!"
        )
    )
)

if not defined MYSQL_PORT (
    for %%P in (3306 3307 3308 3309) do (
        if not defined MYSQL_PORT (
            netstat -ano 2>nul | findstr /R /C:":%%P " | findstr "LISTENING" >nul 2>&1
            if errorlevel 1 (
                set "MYSQL_PORT=%%P"
            )
        )
    )
)
if not defined MYSQL_PORT set "MYSQL_PORT=3307"

REM ====================================================
REM 4. Determine Apache Port
REM ====================================================
set "APACHE_PORT="

if defined SAVED_APACHE_PORT (
    set "APACHE_PORT=!SAVED_APACHE_PORT!"
)

if not defined APACHE_PORT (
    for %%P in (8080 8585 8888 9090 8000 80) do (
        if not defined APACHE_PORT (
            netstat -ano 2>nul | findstr /R /C:":%%P " | findstr "LISTENING" >nul 2>&1
            if errorlevel 1 (
                set "APACHE_PORT=%%P"
            )
        )
    )
)
if not defined APACHE_PORT set "APACHE_PORT=8080"

REM ====================================================
REM 5. Generate configuration files
REM ====================================================
call "%SCRIPTS_DIR%\generate_configs.bat" %APACHE_PORT% %MYSQL_PORT%

REM ====================================================
REM 6. Start MySQL (if not already running)
REM ====================================================
set "MYSQL_READY=0"
"%MYSQL_DIR%\bin\mysqladmin.exe" --host=127.0.0.1 --port=%MYSQL_PORT% -u root ping >nul 2>&1
if not errorlevel 1 (
    set "MYSQL_READY=1"
) else (
    start "" /B "%MYSQL_DIR%\bin\mysqld.exe" --defaults-file="%CONFIG_DIR%\my.ini" --standalone
    for /L %%i in (1,1,15) do (
        if !MYSQL_READY!==0 (
            "%MYSQL_DIR%\bin\mysqladmin.exe" --host=127.0.0.1 --port=%MYSQL_PORT% -u root ping >nul 2>&1
            if not errorlevel 1 (
                set "MYSQL_READY=1"
            ) else (
                timeout /t 1 /nobreak >nul 2>&1
            )
        )
    )
)

REM ====================================================
REM 7. Verify Database tables exist and are accessible
REM ====================================================
if !MYSQL_READY!==1 (
    "%MYSQL_DIR%\bin\mysql.exe" --host=127.0.0.1 --port=%MYSQL_PORT% -u root -e "SELECT id FROM cafe_management.users LIMIT 1;" >nul 2>&1
    if errorlevel 1 (
        call "%SCRIPTS_DIR%\init_db.bat" %MYSQL_PORT% "%ROOT_DIR%"
    )
)

REM ====================================================
REM 8. Start Apache (if not already running)
REM ====================================================
netstat -ano 2>nul | findstr /R /C:":%APACHE_PORT% " | findstr "LISTENING" >nul 2>&1
if errorlevel 1 (
    start "" /B "%APACHE_DIR%\bin\httpd.exe" -d "%ROOT_FWD%/apache" -f "%ROOT_FWD%/config/httpd.conf"
    timeout /t 2 /nobreak >nul 2>&1
)

REM ====================================================
REM 9. Open Browser
REM ====================================================
if "%APACHE_PORT%"=="80" (
    start "" "http://127.0.0.1/Store-System/"
) else (
    start "" "http://127.0.0.1:%APACHE_PORT%/Store-System/"
)

exit /b 0