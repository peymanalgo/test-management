@echo off
rem اجرای آزمون‌یار از طریق دابل‌کلیک — start.sh را فراخوانی می‌کند
setlocal
cd /d "%~dp0"
set "BASH="
where bash >nul 2>&1 && set "BASH=bash"
if not defined BASH if exist "C:\Program Files\Git\bin\bash.exe" set "BASH=C:\Program Files\Git\bin\bash.exe"
if not defined BASH if exist "C:\Program Files (x86)\Git\bin\bash.exe" set "BASH=C:\Program Files (x86)\Git\bin\bash.exe"
if not defined BASH (
    echo Git Bash پیدا نشد. Git for Windows را نصب کنید یا start.sh را مستقیم اجرا کنید.
    pause
    exit /b 1
)
"%BASH%" start.sh %*
pause
