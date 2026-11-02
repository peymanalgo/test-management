@echo off
rem ساخت بستهٔ انتشار آزمون‌یار (azmoon-yar-release.zip) — برای آپلود روی هاست cPanel
rem شامل vendor (بدون نیاز به Composer روی هاست)؛ بدون .env و فایل‌های موقت و دیتابیس محلی
setlocal
cd /d "%~dp0.."
set STAGE=%TEMP%\azmoon-release-stage
set OUT=%CD%\azmoon-yar-release.zip

rem پیدا کردن PHP (wamp64 رایج؛ در غیر این صورت PATH)
set PHP_BIN=php
if not exist "%PHP_BIN%" for %%P in (
  "C:\wamp64\bin\php\php8.4.15\php.exe"
  "C:\wamp64\bin\php\php8.3.28\php.exe"
  "C:\wamp64\bin\php\php8.2.29\php.exe"
  "C:\xampp\php\php.exe"
) do if not exist "%PHP_BIN%" if exist %%P set PHP_BIN=%%~P
if not exist "%PHP_BIN%" (
  echo PHP پیدا نشد — wamp/xampp نصب باشد یا php را در PATH بگذارید.
  pause & exit /b 1
)

if exist "%OUT%" del "%OUT%"
if exist "%STAGE%" rmdir /s /q "%STAGE%"

robocopy "%CD%" "%STAGE%" /MIR /NFL /NDL /NJH /NJS /NP ^
  /XD "%CD%\.git" "%CD%\tools" "%CD%\_scaffold" "%CD%\writable\cache" "%CD%\writable\logs" "%CD%\writable\session" "%CD%\writable\debugbar" ^
  /XF ".env" "composer-setup.php" "start.sh" "start.bat" "azmoon-yar-release.zip" >nul

"%PHP_BIN%" deploy\make-release.php "%STAGE%" "%OUT%"
rmdir /s /q "%STAGE%"

if exist "%OUT%" (
  echo -------------------------------------
  echo  بستهٔ انتشار آماده شد:
  echo  %OUT%
  echo  این فایل را در File Manager هاست در پوشهٔ home آپلود و Extract کنید.
  echo -------------------------------------
) else (
  echo خطا در ساخت بسته!
)
pause
