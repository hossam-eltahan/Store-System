@echo off
title Store System - 1-Click Update Builder
color 0b

echo ========================================================
echo         STORE SYSTEM - 1-CLICK UPDATE BUILDER
echo ========================================================
echo.

if exist "C:\xampp\php\php.exe" (
    set PHP_BIN=C:\xampp\php\php.exe
) else if exist "C:\StoreSystem\php\php.exe" (
    set PHP_BIN=C:\StoreSystem\php\php.exe
) else (
    set PHP_BIN=php
)

"%PHP_BIN%" "C:\xampp\htdocs\Store-System\tools\auto_packager.php"

echo.
pause
