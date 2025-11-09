# BPJS Monitoring Dashboard - Advanced Features

## 🚀 Enhanced System Overview

Dashboard monitoring BPJS telah berhasil ditingkatkan dengan sistem enterprise-grade yang mencakup:

### 1. **Database-Driven Architecture**
- **Historical Logging**: Semua data monitoring disimpan ke database SQLite
- **Performance Tracking**: Menyimpan response time, status, dan error details
- **Scalable Schema**: Tabel terstruktur dengan indexing untuk performa optimal

### 2. **Advanced Alert System**
- **Real-time Alerts**: Sistem peringatan otomatis untuk gangguan
- **Alert Types**:
  - Consecutive Error Alerts (gagal berturut-turut)
  - Response Time Alerts (waktu respons lambat)
  - Timeout Alerts (koneksi timeout)
- **Alert Management**: Resolve alerts langsung dari dashboard
- **Smart Notifications**: Menghindari spam dengan alert deduplication

### 3. **Historical Analytics & Trends**
- **24-Hour Charts**: Grafik performa 24 jam terakhir
- **Trend Analysis**: Analisis tren improving/declining/stable
- **Performance Metrics**:
  - Uptime percentage (current vs 24h)
  - Average response time (current vs 24h)
  - Success rate trends
  - Historical data aggregation

### 4. **Enhanced UI/UX**
- **Alert Banner**: Visual indicator untuk active alerts
- **Performance Severity**: Badge system (excellent/good/slow/critical)
- **Interactive Charts**: Hover tooltips dengan detail
- **Auto-refresh Control**: Toggle manual/auto refresh
- **Responsive Design**: Mobile-friendly interface

### 5. **Smart Configuration Management**
- **Dynamic Endpoints**: Konfigurasi endpoint tersimpan di database
- **Threshold Settings**: Custom warning/critical thresholds per endpoint
- **Timeout Configuration**: Configurable timeout per endpoint
- **Custom Headers**: Support untuk header khusus per endpoint

## 📊 Technical Implementation

### Database Tables:
1. **bpjs_monitoring_logs**: Historical data storage
2. **bpjs_monitoring_alerts**: Alert management
3. **bpjs_endpoint_configs**: Endpoint configurations

### Models:
- `BpjsMonitoringLog`: Provides historical data methods
- `BpjsMonitoringAlert`: Alert management with auto-resolution
- `BpjsEndpointConfig`: Dynamic endpoint configuration

### API Endpoints:
- `GET /bpjs-monitoring/data` - Real-time monitoring data
- `GET /bpjs-monitoring/historical` - Historical analytics
- `GET /bpjs-monitoring/alerts` - Alert management
- `POST /bpjs-monitoring/alerts/{id}/resolve` - Resolve alerts

## 🔧 Advanced Features in Detail

### 1. **Intelligent Monitoring**
```php
// Automatic endpoint seeding
private function seedEndpointConfigs()

// Performance severity calculation
public function getResponseTimeSeverity($responseTime)

// Historical data aggregation
public static function getHistoricalData($endpointName, $hours, $interval)
```

### 2. **Alert Management**
```php
// Consecutive error detection
private function checkForAlerts($config, $responseTime, $status)

// Smart alert resolution
public function resolve()

// Alert deduplication
$existingAlert = BpjsMonitoringAlert::active()->forEndpoint($config->name)
```

### 3. **Performance Analytics**
```php
// Uptime calculation
public static function getUptimePercentage($endpointName, $hours)

// Trend analysis
private function calculateTrend($data, $field)

// Statistical aggregation
public static function getAverageResponseTime($endpointName, $hours)
```

## 📈 Dashboard Features

### Summary Cards:
- **Total Endpoints**: Jumlah endpoint yang dimonitor
- **Uptime**: Success rate dengan tren 24 jam
- **Avg Response Time**: Waktu respons rata-rata dengan tren
- **Status Distribution**: Breakdown success vs error

### Charts:
- **24-Hour Trend**: Dual-axis chart (response time + uptime)
- **Real-time Updates**: Auto-refresh setiap 30 detik
- **Interactive Elements**: Hover untuk detail data

### Endpoint Table:
- **Status Indicators**: Visual status dengan warna
- **Performance Badges**: Severity classification
- **Response Time Bars**: Visual performance indicator
- **Description Support**: Endpoint descriptions dari config

## 🛠️ Configuration

### Environment Setup:
```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

### Caching:
- Response caching (30 seconds) untuk performa
- Database query optimization dengan indexes
- Memory-efficient data structures

## 🚨 Alert Configuration

### Default Thresholds:
- **Warning**: 1000ms response time
- **Critical**: 2000ms response time
- **Consecutive Errors**: 3 failures triggers alert
- **Timeout**: 10 seconds per request

### Customization:
Setiap endpoint dapat dikonfigurasi dengan threshold khusus melalui tabel `bpjs_endpoint_configs`.

## 📱 Mobile Responsive

Dashboard sepenuhnya responsive dengan:
- Grid layout yang adaptif
- Touch-friendly controls
- Optimized untuk tablet dan mobile
- Accessible design patterns

## 🔄 Auto-Refresh System

- **Default**: Auto-refresh aktif (30 detik)
- **Manual Control**: Toggle auto/manual mode
- **Background Updates**: Non-blocking refresh
- **Error Handling**: Graceful degradation pada network issues

## 🎯 Performance Optimizations

1. **Database Indexing**: Optimal query performance
2. **Caching Layer**: Reduced API calls
3. **Lazy Loading**: Efficient data loading
4. **Memory Management**: Cleanup old data automatically
5. **Connection Pooling**: Efficient HTTP client usage

## 📋 Usage

1. **Access Dashboard**: Navigate to `/bpjs-monitoring`
2. **View Real-time Data**: Auto-refreshing every 30 seconds
3. **Manage Alerts**: Click resolve on alert banner
4. **Historical Analysis**: View 24-hour trends
5. **Configuration**: Modify thresholds in database

## 🚀 Future Development Ideas

1. **WhatsApp Integration**: Instant notifications
2. **Email Alerts**: SMTP-based notifications
3. **API Health Scoring**: Composite health metrics
4. **Multi-tenant Support**: Multiple BPJS environments
5. **Advanced Analytics**: ML-based anomaly detection
6. **Export Features**: PDF/Excel reports
7. **Custom Dashboards**: User-configurable views
8. **Geographic Monitoring**: Multi-region support

---

*Dashboard ini telah dikembangkan dari sistem monitoring sederhana menjadi platform enterprise-grade dengan fitur-fitur canggih untuk monitoring API BPJS secara real-time dan historical analysis.*
