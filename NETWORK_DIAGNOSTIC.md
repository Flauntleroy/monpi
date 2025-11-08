# Network Diagnostic Dashboard

## 🌐 Overview
Real-time network diagnostic dashboard untuk monitoring dan analisis koneksi BPJS dan baseline endpoints.

## 📊 Features & Metrics

### 1. Current Status
- Status terkini semua endpoints (BPJS & Baseline)
- Response codes
- Response times
- Timestamp monitoring

### 2. Response Times Analysis
- Current & average response time per endpoint
- Historical latency trends
- Performance benchmarking

### 3. Status History
- 20 status check terakhir
- Pattern recognition
- Error trend analysis

### 4. Uptime Statistics
- Uptime percentage per endpoint
- Success/failure ratio
- Reliability metrics

### 5. Latency Comparison
- BPJS vs Baseline performance
- Latency ratio & differences
- Performance gap analysis

### 6. Smart Diagnosis
Analisis otomatis untuk masalah:
- Koneksi internet
- Server BPJS
- Network performance
- Anomali latency

### 7. Action Recommendations
Saran tindakan berdasarkan:
- Status terkini
- Historical patterns
- Performance metrics

## 🔄 API Reference

### Get Diagnostic Data
```http
GET /api/network-diagnostic
```

#### Response Format
```json
{
    "current_status": {
        "bpjs": [...],
        "baseline": [...]
    },
    "response_times": {
        "bpjs": [...],
        "baseline": [...]
    },
    "uptime_stats": {
        "bpjs": [...],
        "baseline": [...]
    },
    "latency_comparison": {
        "bpjs_avg": 145.5,
        "baseline_avg": 48.3,
        "difference": 97.2,
        "ratio": 3.01
    },
    "diagnosis": [...],
    "recommendations": [...]
}
```

## 📈 Dashboard View
Cara menggunakan dashboard:

1. **Real-time Monitoring**
   - Lihat status terkini semua endpoints
   - Monitor response time trends
   - Track uptime statistics

2. **Performance Analysis**
   - Compare BPJS vs baseline latency
   - Analyze historical patterns
   - Identify bottlenecks

3. **Troubleshooting**
   - Review diagnosis results
   - Follow recommended actions
   - Track issue resolution

4. **Historical Analysis**
   - View status history
   - Analyze performance trends
   - Track reliability metrics

## 🎯 Interpreting Results

### Response Time Indicators
- 🟢 < 100ms: Excellent
- 🟡 100-300ms: Good
- 🟠 300-500ms: Fair
- 🔴 > 500ms: Poor

### Latency Ratio Analysis
- Ratio < 1.5: Optimal
- Ratio 1.5-2: Acceptable
- Ratio > 2: Needs investigation

### Status Codes
- 2xx: Success
- 4xx: Client/Auth Error
- 5xx: Server Error

## 🚀 Best Practices

1. **Regular Monitoring**
   - Check dashboard setiap jam
   - Review historical data mingguan
   - Analyze trends bulanan

2. **Issue Response**
   - Follow recommended actions
   - Document troubleshooting steps
   - Track resolution effectiveness

3. **Performance Optimization**
   - Monitor latency trends
   - Implement suggested improvements
   - Validate optimizations
