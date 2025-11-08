# 🎯 **Fitur Custom Endpoints - BPJS Monitoring Dashboard**

## ✨ **Fitur Baru yang Ditambahkan:**

### 📍 **1. Custom Endpoint Management**
- **Add Custom Endpoints**: Tambah endpoint API apapun untuk dipantau
- **localStorage Persistence**: Semua custom endpoints disimpan di browser
- **Flexible Configuration**: HTTP method, timeout, headers custom
- **Active/Inactive Toggle**: Kontrol endpoint mana yang dipantau

### 🛠️ **2. User Interface**
- **Add Endpoint Button**: Tombol "Add Endpoint" di header dashboard
- **Manage Button**: Tombol "Manage" dengan counter jumlah endpoints
- **Custom Indicator**: Badge "Custom" pada endpoint yang ditambah user
- **Inline Actions**: Edit/Delete buttons langsung di tabel

### 📋 **3. Fitur Form Custom Endpoint**
- **Name**: Nama endpoint (required)
- **URL**: URL endpoint yang akan dipantau (required) 
- **Description**: Deskripsi opsional
- **HTTP Method**: GET/POST
- **Timeout**: Custom timeout (1-60 detik)
- **Active Status**: Toggle aktif/non-aktif

### 🎨 **4. Modal Management**
- **Add/Edit Modal**: Form untuk tambah/edit endpoint
- **Manage Modal**: Daftar semua custom endpoints
- **Responsive Design**: Works di mobile dan desktop

## 🚀 **Cara Menggunakan:**

### **Menambah Endpoint Baru:**
1. Klik tombol **"Add Endpoint"** di header
2. Isi form:
   - **Name**: Contoh "My API"
   - **URL**: Contoh "https://api.mysite.com/health"
   - **Description**: Contoh "Health check endpoint"
   - **Method**: Pilih GET atau POST
   - **Timeout**: Set timeout (default 10 detik)
3. Centang **"Active"** untuk include dalam monitoring
4. Klik **"Add Endpoint"**

### **Mengelola Endpoints:**
1. Klik tombol **"Manage (X)"** di header
2. Lihat daftar semua custom endpoints
3. **Enable/Disable**: Toggle status aktif
4. **Edit**: Klik icon edit untuk ubah konfigurasi
5. **Delete**: Klik icon trash untuk hapus

### **Monitoring Custom Endpoints:**
- Custom endpoints akan muncul di tabel dengan badge **"Custom"**
- Response time, status, dan severity akan ditampilkan real-time
- Summary cards akan include custom endpoints dalam perhitungan
- Auto-refresh setiap 30 detik include custom endpoints

## 💾 **Data Storage:**
- **LocalStorage Key**: `bpjs_custom_endpoints`
- **Data Format**: JSON array dengan struktur:
```json
[
  {
    "id": "1691234567890",
    "name": "My API",
    "url": "https://api.example.com/health",
    "description": "Health check endpoint",
    "method": "GET",
    "headers": {},
    "timeout": 10,
    "isActive": true
  }
]
```

## 🔧 **Technical Details:**
- **CORS**: Custom endpoints akan ditest langsung dari browser
- **Timeout Handling**: Auto-timeout sesuai konfigurasi
- **Error Handling**: Network errors ditangani dengan status "timeout"/"error"
- **Performance**: Custom endpoints ditest parallel dengan default endpoints
- **Real-time Updates**: Summary dan statistics dihitung ulang dengan custom data

## 🎉 **Benefits:**
✅ **Fleksibilitas Penuh**: Monitor API apapun, tidak terbatas BPJS  
✅ **Easy Management**: UI intuitif untuk CRUD endpoints  
✅ **Persistent Storage**: Settings tersimpan di browser  
✅ **Real-time Integration**: Langsung terintegrasi dengan dashboard  
✅ **No Backend Changes**: Semua di frontend, tidak perlu ubah server  

Fitur ini memberikan **kontrol penuh** kepada user untuk memantau endpoint API apapun sesuai kebutuhan mereka! 🚀
