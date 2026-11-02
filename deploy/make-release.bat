@echo off
rem ساخت بستهٔ انتشار آزمون‌یار (azmoon-yar-release.zip) از سورس محلی — برای آپلود روی هاست cPanel
rem شامل vendor (بدون نیاز به Composer روی هاست)؛ بدون .env، دیتابیس محلی و فایل‌های موقت
setlocal
cd /d "%~dp0.."
set STAGE=%TEMP%\azmoon-release-stage
set OUT=%CD%\azmoon-yar-release.zip
if exist "%OUT%" del "%OUT%"
if exist "%STAGE%" rmdir /s /q "%STAGE%"

robocopy "%CD%" "%STAGE%" /MIR /NFL /NDL /NJH /NJS /NP ^
  /XD "%CD%\.git" "%CD%\tools" "%CD%\_scaffold" "%CD%\writable\cache" "%CD%\writable\logs" "%CD%\writable\session" "%CD%\writable\debugbar" ^
  /XF ".env" "composer-setup.php" "start.sh" "start.bat" "azmoon-yar-release.zip" >nul

powershell -NoProfile -Command "Compress-Archive -Path '%STAGE%\*' -DestinationPath '%OUT%' -Force"
rmdir /s /q "%STAGE%"

if exist "%OUT%" (
  echo -------------------------------------
  echo  بستهٔ انتشار آماده شد:
  echo  %OUT%
  powershell -NoProfile -Command "Write-Output ('  Size: ' + [Math]::Round((Get-Item '%OUT%').Length/1MB,1) + ' MB')"
  echo  این فایل را در File Manager هاست در پوشهٔ home آپلود و Extract کنید.
  echo -------------------------------------
) else (
  echo خطا در ساخت بسته!
)
pause
