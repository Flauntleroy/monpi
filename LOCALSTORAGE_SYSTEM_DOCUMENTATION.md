# BPJS Monitoring Dashboard - LocalStorage Version

## Overview
Sistem monitoring BPJS yang telah diupgrade ke arsitektur localStorage-only untuk menghilangkan dependency database dan meningkatkan performa. Sistem ini menggunakan browser localStorage untuk menyimpan data historis dan pengaturan endpoint custom.

## Key Features

### 1. Database-Free Architecture
- **Tidak ada dependency database** - sistem berjalan tanpa SQLite, MySQL, atau database lainnya
- **Pure localStorage** - semua data historis dan pengaturan disimpan di browser
- **No migrations needed** - tidak perlu setup database atau migration

### 2. Advanced Network Diagnostics
- **4-Category Analysis**:
  - Local Connectivity (ping gateway, DNS servers)
  - DNS Resolution (resolve domain names)
  - External Connectivity (test external APIs)
  - BPJS Infrastructure (test BPJS endpoints)
- **Root Cause Analysis** dengan confidence level
- **Actionable Recommendations** untuk troubleshooting

### 3. Custom Endpoint Management
- **Add/Edit/Delete** endpoint custom
- **Auto-detection** BPJS endpoints (menggunakan BPJS authentication otomatis)
- **BPJS Authentication Integration** - consid, secretkey, userkey otomatis untuk endpoints *.bpjs-kesehatan.go.id
- **Enable/Disable** endpoint individual
- **localStorage persistence** - pengaturan tersimpan di browser

### 4. Real-time Monitoring & Charts
- **ApexCharts integration** dengan dark/light mode support
- **Response Time Overview** (bar chart)
- **Status Distribution** (donut chart)
- **Historical Trends** dari localStorage data
- **Auto-refresh** setiap 30 detik

### 5. Enhanced UI/UX
- **3-column responsive grid** untuk endpoint status
- **Custom tooltip styling** untuk chart readability
- **Modal management** untuk endpoint configuration
- **Real-time status indicators**

## Technical Architecture

### Frontend Components
- **DashboardLocalStorage.vue** - Main dashboard dengan localStorage integration
- **ApexCharts** untuk data visualization
- **Tailwind CSS** untuk responsive design
- **Vue 3 Composition API** untuk state management

### Backend Controller
- **BpjsMonitoringControllerLocalStorage.php** - Zero database dependencies
- **Network diagnostics engine** dengan comprehensive testing
- **BPJS API authentication** dengan consid, secretkey, userkey integration
- **Custom endpoint testing** dengan auto-detection BPJS vs external endpoints

### Data Storage Strategy
- **Historical Data**: Browser localStorage (max 24 hours, 2880 data points)
- **Custom Endpoints**: Browser localStorage dengan JSON structure
- **No server-side storage** - system adalah stateless di server

## Setup & Installation

### 1. Backend Setup
```bash
# No database setup needed!
cd /path/to/project

# Install PHP dependencies
composer install

# Build frontend assets
npm install
npm run build

# Start server
php artisan serve
```

### 2. Routes Configuration
Route utama sudah dikonfigurasi untuk menggunakan localStorage dashboard:
- `/bpjs-monitoring` - Main dashboard (DashboardLocalStorage.vue)
- `/bpjs-monitoring/data` - API endpoint untuk data monitoring
- `/bpjs-monitoring/test-custom-endpoint` - Test custom endpoints

### 3. Browser Requirements
- **LocalStorage support** (semua modern browsers)
- **JavaScript enabled** untuk Vue.js dan ApexCharts
- **Fetch API support** untuk network requests

## Data Structures

### Historical Data (localStorage)
```javascript
{
  "timestamp": "2025-01-27T10:30:00.000Z",
  "summary": {
    "total": 10,
    "success": 8,
    "error": 2,
    "avg_response_time": 1250.5,
    "uptime_percentage": 80.0
  },
  "endpoints": [
    {
      "name": "Diagnosa",
      "url": "https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/referensi/diagnosa/A00",
      "response_time": 1250.5,
      "code": "200",
      "status": "success",
      "isCustom": false
    }
  ]
}
```

### Custom Endpoints (localStorage)
```javascript
[
  {
    "id": "1738058400000",
    "name": "My Custom API",
    "url": "https://api.example.com/health",
    "description": "Health check endpoint",
    "method": "GET",
    "headers": {},
    "timeout": 10,
    "isActive": true,
    "isBpjsEndpoint": false,
    "useProxy": false
  }
]
```

## Network Diagnostics

### Diagnostic Categories
1. **Local Connectivity**
   - Ping 8.8.8.8 (Google DNS)
   - Ping 1.1.1.1 (Cloudflare DNS)
   - Check local network gateway

2. **DNS Resolution**
   - Resolve google.com
   - Resolve bpjs-kesehatan.go.id
   - Check DNS response times

3. **External Connectivity**
   - Test Google API
   - Test GitHub API
   - Verify external internet access

4. **BPJS Infrastructure**
   - Test BPJS main domain
   - Test API endpoints
   - Check BPJS server response

### Root Cause Analysis
System menganalisis hasil diagnostics dan memberikan:
- **Root cause identification**: local_network_issue, bpjs_server_issue, all_systems_normal, dll
- **Confidence level**: 0-100% berdasarkan consistency hasil test
- **Actionable recommendations**: steps untuk troubleshooting

## Performance Optimizations

### 1. localStorage Data Management
- **Automatic cleanup**: Hapus data > 24 jam otomatis
- **Efficient storage**: Hanya simpan data essential
- **Batch updates**: Update localStorage sekali per refresh cycle

### 2. Network Request Optimization
- **Parallel testing**: Test multiple endpoints simultaneously
- **Timeout management**: Prevent hung requests
- **Error handling**: Graceful degradation saat network issues

### 3. Frontend Performance
- **Lazy loading**: Components dimuat sesuai kebutuhan
- **Efficient re-renders**: Vue.js reactivity optimization
- **Chart performance**: ApexCharts dengan data limiting

## Troubleshooting

### Common Issues

1. **"No data available" in charts**
   - Tunggu 1-2 refresh cycles untuk data collection
   - Check browser console untuk network errors
   - Verify localStorage tidak penuh

2. **Custom endpoints tidak tersimpan**
   - Check localStorage permissions di browser
   - Verify tidak ada ad-blocker yang block localStorage
   - Clear browser cache dan coba lagi

3. **Network diagnostics tidak muncul**
   - Check internet connection
   - Verify tidak ada firewall yang block external requests
   - Check browser console untuk CORS errors

### Development Mode
```bash
# Run with debug output
npm run dev

# Check API directly
curl http://localhost:8000/bpjs-monitoring/data

# Test custom endpoint
curl -X POST http://localhost:8000/bpjs-monitoring/test-custom-endpoint \
  -H "Content-Type: application/json" \
  -d '{"url":"https://httpbin.org/get","method":"GET","timeout":10}'
```

## Migration from Database Version

### Automatic Migration
System otomatis detect database-free mode, tidak perlu migration manual.

### Data Continuity
- Historical data akan rebuild otomatis dari monitoring real-time
- Custom endpoints perlu di-add ulang via UI (one-time setup)
- Tidak ada data loss untuk monitoring functionality

## Security Considerations

### Client-side Storage
- **Data privacy**: Semua data tersimpan di browser user
- **No server logs**: Tidak ada historical data di server
- **BPJS credentials**: Tetap disimpan di server config, tidak di localStorage

### Network Security
- **CORS handling**: Proper proxy untuk BPJS API access
- **Input validation**: Semua user input divalidasi
- **XSS prevention**: Vue.js built-in protection

## Future Enhancements

### Planned Features
1. **Export/Import settings** - Backup custom endpoints
2. **Advanced alerting** - Browser notifications untuk critical issues
3. **Performance insights** - Deeper analytics dari historical data
4. **Mobile responsiveness** - Enhanced mobile dashboard
5. **Offline mode** - Service worker untuk offline monitoring

### Architecture Improvements
1. **IndexedDB migration** - Untuk storage yang lebih besar
2. **WebSocket integration** - Real-time updates tanpa polling
3. **PWA capabilities** - Install sebagai desktop app
4. **Advanced caching** - Better performance optimization

---

## Contact & Support
Untuk issues atau feature requests, check project repository atau contact development team.

**Teknologi Stack:**
- Backend: Laravel 12 + PHP 8.2
- Frontend: Vue.js 3 + TypeScript + Tailwind CSS
- Charts: ApexCharts
- Storage: Browser localStorage
- Network: Fetch API + Laravel HTTP Client
