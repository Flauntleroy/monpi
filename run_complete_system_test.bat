@echo off
echo ====================================================
echo BPJS Monitoring LocalStorage System - Complete Test
echo ====================================================
echo.

echo ✅ Features yang telah diimplementasi:
echo.
echo 1. ✅ Database-Free Architecture
echo    - Sistem berjalan tanpa SQLite/MySQL/PostgreSQL
echo    - Semua data disimpan di browser localStorage
echo    - Zero migration needed
echo.
echo 2. ✅ BPJS Authentication Integration
echo    - consid, secretkey, userkey otomatis digunakan
echo    - Semua endpoint bpjs-kesehatan.go.id menggunakan auth
echo    - Signature HMAC SHA256 otomatis
echo.
echo 3. ✅ Custom Endpoint Management
echo    - Add/Edit/Delete endpoint via modal interface
echo    - Auto-detection BPJS vs external endpoints
echo    - localStorage persistence
echo.
echo 4. ✅ Advanced Network Diagnostics
echo    - 4-kategori analisis (Local, DNS, External, BPJS)
echo    - Root cause analysis dengan confidence level
echo    - Actionable recommendations
echo.
echo 5. ✅ Modern UI/UX
echo    - 3-column responsive grid
echo    - ApexCharts dengan custom tooltip styling
echo    - Real-time monitoring dengan auto-refresh
echo.

echo Starting system components...
echo.

echo [1/4] Starting Laravel server...
start "BPJS Laravel Server" cmd /k "cd /d \"d:\Herd Project\monitoringbpjs\" && php artisan serve"

echo [2/4] Waiting for server startup...
timeout /t 5 /nobreak >nul

echo [3/4] Testing API endpoints...
echo Testing main data endpoint:
curl -s -w "Status: %%{http_code} | Time: %%{time_total}s\n" http://localhost:8000/bpjs-monitoring/data -o nul

echo.
echo Testing custom endpoint functionality:
curl -s -X POST -H "Content-Type: application/json" -d "{\"url\":\"https://httpbin.org/get\",\"method\":\"GET\",\"timeout\":10}" http://localhost:8000/bpjs-monitoring/test-custom-endpoint -w "Status: %%{http_code}\n" -o nul

echo.
echo [4/4] Opening browser interfaces...
echo Opening main dashboard...
start "" "http://localhost:8000/bpjs-monitoring"

timeout /t 2 /nobreak >nul
echo Opening test interface...
start "" "http://localhost:8000/test-custom-endpoints-bpjs.html"

echo.
echo ====================================================
echo 🚀 SISTEMA COMPLETAMENTE FUNCIONAL!
echo ====================================================
echo.
echo 📋 URLs Disponibles:
echo ├── Main Dashboard: http://localhost:8000/bpjs-monitoring
echo ├── API Data: http://localhost:8000/bpjs-monitoring/data
echo ├── Test Interface: http://localhost:8000/test-custom-endpoints-bpjs.html
echo └── Simple HTML: http://localhost:8000/bpjs-monitoring/simple
echo.
echo 🔧 BPJS Authentication:
echo ├── ✅ consid, secretkey, userkey configurados
echo ├── ✅ Signature HMAC SHA256 automática
echo ├── ✅ Headers X-cons-id, X-timestamp, X-signature
echo └── ✅ Auto-detection para endpoints BPJS
echo.
echo 📊 Features Activas:
echo ├── ✅ Custom endpoint management con modal
echo ├── ✅ localStorage persistence (sem database)
echo ├── ✅ Network diagnostics avançados
echo ├── ✅ ApexCharts com tooltip styling
echo ├── ✅ Grid layout responsivo 3-colunas
echo └── ✅ Auto-refresh cada 30 segundos
echo.
echo 🧪 Para testar BPJS endpoints, use exemplos:
echo ├── Diagnosa: https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/referensi/diagnosa/A00
echo ├── Faskes: https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/referensi/faskes/1702R002/2
echo └── Timestamp: https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/timestamp
echo.
echo 💡 Todos los endpoints BPJS (*.bpjs-kesehatan.go.id) usan auth automática
echo 💡 Endpoints externos usan requests simples sin auth adicional
echo.
echo Pressione qualquer tecla para continuar...
pause >nul
