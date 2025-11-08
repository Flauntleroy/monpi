@echo off
echo Starting BPJS Monitoring LocalStorage System...
echo.

echo Step 1: Starting Laravel server...
start "Laravel Server" cmd /k "cd /d \"d:\Herd Project\monitoringbpjs\" && php artisan serve"

echo Step 2: Waiting for server to start...
timeout /t 3 /nobreak >nul

echo Step 3: Testing API endpoint...
curl -s -w "HTTP Status: %%{http_code}\n" http://localhost:8000/bpjs-monitoring/data | head -20

echo.
echo Step 4: Opening dashboard in browser...
start "" "http://localhost:8000/bpjs-monitoring"

echo.
echo ✅ BPJS Monitoring System Started!
echo.
echo Available URLs:
echo - Dashboard: http://localhost:8000/bpjs-monitoring
echo - API Data: http://localhost:8000/bpjs-monitoring/data
echo - Simple HTML: http://localhost:8000/bpjs-monitoring/simple
echo.
echo Features:
echo - ✅ Database-free localStorage system
echo - ✅ Network diagnostics with root cause analysis
echo - ✅ Custom endpoint management
echo - ✅ Real-time monitoring with charts
echo - ✅ 3-column responsive grid layout
echo - ✅ Dark/light mode chart tooltips
echo.
echo Press any key to continue...
pause >nul
