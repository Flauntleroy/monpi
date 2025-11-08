# BPJS API Monitoring Dashboard

Dashboard monitoring modern dan minimalis untuk memantau konektivitas API BPJS secara real-time.

## 🚀 Fitur Utama

### 1. **Dashboard Overview**
- **Total Endpoints**: Menampilkan jumlah total endpoint yang dipantau (17 endpoint)
- **Uptime Percentage**: Persentase ketersediaan layanan dengan progress bar visual
- **Average Response Time**: Waktu respons rata-rata dengan indikator warna:
  - 🟢 Hijau: < 500ms (Excellent)
  - 🟡 Kuning: 500-1000ms (Good)
  - 🔴 Merah: > 1000ms (Slow)
- **Status Summary**: Ringkasan status success vs error

### 2. **Real-time Monitoring**
- Auto-refresh setiap 30 detik
- Manual refresh dengan tombol
- Timestamp update terakhir
- Loading states dan error handling

### 3. **Response Time Chart**
- Bar chart sederhana untuk trend response time
- Menampilkan 10 data terakhir
- Color-coded berdasarkan performance:
  - Hijau: Response time baik
  - Kuning: Response time sedang  
  - Merah: Response time lambat

### 4. **Endpoint Status Table**
- Status real-time setiap endpoint
- Visual indicator (dot + icon)
- Response time individual
- HTTP status code dengan badge
- URL endpoint lengkap
- Hover effects untuk interaksi

## 🛠 Teknologi Stack

- **Backend**: Laravel 12 + Inertia.js
- **Frontend**: Vue.js 3 + TypeScript
- **Styling**: Tailwind CSS + Shadcn/ui components
- **HTTP Client**: Laravel HTTP client
- **Charts**: Simple CSS-based bar chart (ringan)

## 📊 Endpoint yang Dipantau

### Referensi Data
1. **Diagnosa** - `/referensi/diagnosa/A00`
2. **Poli** - `/referensi/poli/INT`
3. **Faskes** - `/referensi/faskes/0101R001/1`
4. **Dokter DPJP** - `/referensi/dokter/pelayanan/1/tglPelayanan/{date}/Spesialis/INT`
5. **Propinsi** - `/referensi/propinsi`
6. **Kabupaten** - `/referensi/kabupaten/propinsi/01`
7. **Kecamatan** - `/referensi/kecamatan/kabupaten/0101`
8. **Procedure** - `/referensi/procedure/001`
9. **Kelas Rawat** - `/referensi/kelasrawat`
10. **Dokter** - `/referensi/dokter/A`
11. **Spesialistik** - `/referensi/spesialistik`
12. **Ruang Rawat** - `/referensi/ruangrawat`
13. **Cara Keluar** - `/referensi/carakeluar`
14. **Pasca Pulang** - `/referensi/pascapulang`

### Data Rujukan
15. **Rujukan by NoRujukan** - `/Rujukan/170205010525Y000103`
16. **Rujukan by NoKartu** - `/Rujukan/Peserta/0002657364478`
17. **Rujukan by TglRujukan** - `/Rujukan/List/TglRujukan/{date}`

## 🔧 Konfigurasi BPJS

Kredensial BPJS (dapat diubah di `BpjsMonitoringController.php`):
```php
private $api_url = 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/';
private $consid = '17432';
private $secretkey = '3nK53BBE23';
private $user_key = '1823bb1d8015aee02180ee12d2af2b2c';
```

## 🌐 Routes

- **Dashboard**: `/bpjs-monitoring` (GET)
- **API Data**: `/api/bpjs-monitoring/data` (GET)

## 📱 Responsive Design

Dashboard fully responsive dengan breakpoints:
- **Mobile**: 1 kolom
- **Tablet**: 2 kolom
- **Desktop**: 4 kolom
- **Large**: 4 kolom dengan spacing optimal

## 🎨 Design System

### Color Palette
- **Primary**: Blue (#3B82F6)
- **Success**: Green (#10B981)
- **Warning**: Yellow (#F59E0B)
- **Error**: Red (#EF4444)
- **Gray**: Neutral tones for text and backgrounds

### Components
- **Cards**: Clean white cards with subtle shadows
- **Badges**: Rounded status indicators
- **Progress Bars**: Smooth animated progress indicators
- **Icons**: Lucide icons untuk konsistensi
- **Hover Effects**: Subtle transitions untuk interaksi

## 🚦 Status Indicators

### Visual Indicators
- **🟢 Green Dot**: Endpoint healthy (HTTP 200)
- **🔴 Red Dot**: Endpoint error (Non-200 response)
- **⚪ Gray Dot**: Unknown status

### Response Time Colors
- **Green Text**: < 500ms
- **Yellow Text**: 500-1000ms  
- **Red Text**: > 1000ms

## ⚡ Performance

- **Lightweight**: Tanpa library chart yang berat
- **Efficient**: API calls dengan timeout 10 detik
- **Smooth**: CSS transitions dan animations
- **Auto-refresh**: 30 detik interval (dapat diubah)
- **Error Recovery**: Graceful error handling

## 📋 API Response Format

```json
{
  "summary": {
    "total": 17,
    "success": 16,
    "error": 1,
    "avg_response_time": 750.25,
    "uptime_percentage": 94.12
  },
  "endpoints": [
    {
      "name": "Diagnosa",
      "url": "https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/referensi/diagnosa/A00",
      "response_time": 620.5,
      "code": "200",
      "message": "OK",
      "status": "success"
    }
  ],
  "timestamp": "2025-01-31 14:30:15"
}
```

## 🔒 Security Features

- **HMAC Signature**: Proper BPJS API authentication
- **Request Timeout**: 10 detik timeout untuk mencegah hanging
- **Error Sanitization**: Proper error message handling
- **CORS Handling**: Proper API response headers

## 📈 Monitoring Metrics

Dashboard menyediakan insight untuk:
- **Service Availability**: Uptime percentage
- **Performance**: Response time trends
- **Reliability**: Success/error ratios
- **Endpoint Health**: Individual endpoint status

## 🔄 Auto-refresh Behavior

- **Interval**: 30 detik
- **Background Updates**: Seamless data refresh
- **Chart Updates**: Dynamic chart data update
- **Error Resilience**: Continues monitoring meski ada error
- **Manual Control**: Tombol refresh manual tersedia

Dashboard ini dirancang khusus untuk memberikan visibilitas real-time terhadap kesehatan API BPJS dengan interface yang clean, modern, dan mudah dipahami oleh semua stakeholder.
