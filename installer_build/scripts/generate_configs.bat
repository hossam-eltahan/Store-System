@echo off
setlocal EnableDelayedExpansion
chcp 65001 >nul 2>&1

set "APACHE_PORT=%1"
set "MYSQL_PORT=%2"
if "%APACHE_PORT%"=="" set "APACHE_PORT=8080"
if "%MYSQL_PORT%"=="" set "MYSQL_PORT=3307"

set "ROOT_DIR=%~dp0.."
pushd "%ROOT_DIR%"
set "ROOT_DIR=%CD%"
popd

set "CONFIG_DIR=%ROOT_DIR%\config"
set "APP_CONFIG_DIR=%ROOT_DIR%\htdocs\Store-System\config"

if not exist "%CONFIG_DIR%" mkdir "%CONFIG_DIR%"
if not exist "%APP_CONFIG_DIR%" mkdir "%APP_CONFIG_DIR%"
if not exist "%ROOT_DIR%\tmp" mkdir "%ROOT_DIR%\tmp"
if not exist "%ROOT_DIR%\logs" mkdir "%ROOT_DIR%\logs"
if not exist "%ROOT_DIR%\data" mkdir "%ROOT_DIR%\data"

set "ROOT_FWD=%ROOT_DIR:\=/%"

REM Save ports.ini
(
echo [ports]
echo apache_port=%APACHE_PORT%
echo mysql_port=%MYSQL_PORT%
) > "%CONFIG_DIR%\ports.ini"

(
echo [ports]
echo apache_port=%APACHE_PORT%
echo mysql_port=%MYSQL_PORT%
) > "%APP_CONFIG_DIR%\ports.ini"

echo [OK] Ports saved: Apache=%APACHE_PORT%, MySQL=%MYSQL_PORT%

REM Generate Apache httpd.conf
(
echo ServerRoot "%ROOT_FWD%/apache"
echo Listen %APACHE_PORT%
echo ServerName 127.0.0.1:%APACHE_PORT%
echo.
echo LoadModule access_compat_module modules/mod_access_compat.so
echo LoadModule actions_module modules/mod_actions.so
echo LoadModule alias_module modules/mod_alias.so
echo LoadModule authz_core_module modules/mod_authz_core.so
echo LoadModule authz_host_module modules/mod_authz_host.so
echo LoadModule dir_module modules/mod_dir.so
echo LoadModule env_module modules/mod_env.so
echo LoadModule mime_module modules/mod_mime.so
echo LoadModule rewrite_module modules/mod_rewrite.so
echo LoadModule setenvif_module modules/mod_setenvif.so
echo LoadModule php_module "%ROOT_FWD%/php/php8apache2_4.dll"
echo.
echo PHPIniDir "%ROOT_FWD%/config"
echo.
echo DocumentRoot "%ROOT_FWD%/htdocs"
echo ^<Directory "%ROOT_FWD%/htdocs"^>
echo     Options Indexes FollowSymLinks Includes ExecCGI
echo     AllowOverride All
echo     Require all granted
echo ^</Directory^>
echo.
echo ^<IfModule dir_module^>
echo     DirectoryIndex index.php index.html
echo ^</IfModule^>
echo.
echo ^<FilesMatch "\.php$"^>
echo     SetHandler application/x-httpd-php
echo ^</FilesMatch^>
echo.
echo AddType application/x-httpd-php .php
echo.
echo TypesConfig "%ROOT_FWD%/apache/conf/mime.types"
echo.
echo ErrorLog "%ROOT_FWD%/logs/apache_error.log"
echo LogLevel warn
echo.
echo AcceptFilter http none
echo AcceptFilter https none
echo EnableSendfile off
echo EnableMMAP off
) > "%CONFIG_DIR%\httpd.conf"

REM Generate MySQL my.ini
(
echo [mysqld]
echo port=%MYSQL_PORT%
echo basedir="%ROOT_FWD%/mysql"
echo datadir="%ROOT_FWD%/data"
echo tmpdir="%ROOT_FWD%/tmp"
echo socket="%ROOT_FWD%/tmp/mysql.sock"
echo pid-file="%ROOT_FWD%/tmp/mysql.pid"
echo log-error="%ROOT_FWD%/logs/mysql_error.log"
echo.
echo default-storage-engine=InnoDB
echo character-set-server=utf8mb4
echo collation-server=utf8mb4_unicode_ci
echo.
echo innodb_buffer_pool_size=128M
echo innodb_log_file_size=48M
echo innodb_file_per_table=1
echo max_connections=100
echo max_allowed_packet=64M
echo tmp_table_size=32M
echo max_heap_table_size=32M
echo skip-name-resolve
echo.
echo [client]
echo port=%MYSQL_PORT%
echo default-character-set=utf8mb4
echo.
echo [mysql]
echo default-character-set=utf8mb4
) > "%CONFIG_DIR%\my.ini"

REM Generate PHP php.ini
(
echo [PHP]
echo engine = On
echo short_open_tag = Off
echo precision = 14
echo output_buffering = 4096
echo max_execution_time = 300
echo max_input_time = 60
echo memory_limit = 256M
echo error_reporting = E_ALL ^& ~E_DEPRECATED ^& ~E_STRICT
echo display_errors = Off
echo log_errors = On
echo error_log = "%ROOT_FWD%/logs/php_error.log"
echo post_max_size = 64M
echo default_charset = "UTF-8"
echo file_uploads = On
echo upload_max_filesize = 20M
echo max_file_uploads = 20
echo date.timezone = Africa/Cairo
echo.
echo extension_dir = "%ROOT_FWD%/php/ext"
echo.
echo extension=curl
echo extension=fileinfo
echo extension=gd
echo extension=mbstring
echo extension=mysqli
echo extension=openssl
echo extension=pdo_mysql
echo extension=zip
echo.
echo [mbstring]
echo mbstring.internal_encoding = UTF-8
echo mbstring.http_output = UTF-8
echo.
echo [Session]
echo session.save_handler = files
echo session.save_path = "%ROOT_FWD%/tmp"
echo session.use_strict_mode = 0
echo session.use_cookies = 1
echo session.cookie_httponly = 1
echo session.gc_maxlifetime = 1440
) > "%CONFIG_DIR%\php.ini"

exit /b 0