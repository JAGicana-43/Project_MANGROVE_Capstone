@echo off
REM Starts the local Laravel server and a Cloudflare tunnel to it.
REM Double-click this file any time you want a public URL for testing.
REM Each run gives a NEW random trycloudflare.com URL -- read it from the
REM second window that opens, and update the mobile app with it.

cd /d "%~dp0"

echo Starting Laravel server (php artisan serve)...
start "MANGROVE - Laravel Server" cmd /k php artisan serve

timeout /t 3 /nobreak >nul

echo Starting Cloudflare tunnel...
echo (Your public URL will appear in the new window in a few seconds.)
start "MANGROVE - Public URL (Cloudflare Tunnel)" cmd /k .tools\cloudflared.exe tunnel --url http://localhost:8000

echo.
echo Two windows just opened:
echo   1. Laravel Server   - keep this open, it's your actual app
echo   2. Public URL       - look here for the https://....trycloudflare.com link
echo.
echo Close both windows when you're done testing.
pause
