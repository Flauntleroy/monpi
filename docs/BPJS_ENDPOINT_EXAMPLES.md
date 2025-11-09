# BPJS Endpoint Examples untuk Custom Endpoint

## Endpoint BPJS Kesehatan (Otomatis menggunakan authentication)

### 1. Referensi Data
```
Nama: Diagnosa ICD-10
URL: https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/referensi/diagnosa/A00
Deskripsi: Referensi data diagnosa berdasarkan kode ICD-10

Nama: Poli/Spesialis
URL: https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/referensi/poli/INT
Deskripsi: Referensi data poliklinik/spesialis

Nama: Faskes Tingkat 1
URL: https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/referensi/faskes/1702R002/2
Deskripsi: Data fasilitas kesehatan tingkat 1

Nama: Dokter DPJP
URL: https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/referensi/dokter/1702R002
Deskripsi: Data dokter penanggung jawab pelayanan
```

### 2. Data Peserta
```
Nama: Peserta by NIK
URL: https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/Peserta/nik/6304151101990001/tglSEP/2025-07-31
Deskripsi: Data peserta berdasarkan NIK

Nama: Peserta by Nomor Kartu
URL: https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/Peserta/nokartu/0001234567890/tglSEP/2025-07-31
Deskripsi: Data peserta berdasarkan nomor kartu BPJS
```

### 3. Monitoring & System
```
Nama: Timestamp Server
URL: https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/timestamp
Deskripsi: Waktu server BPJS untuk sinkronisasi

Nama: Monitoring Aplikasi
URL: https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/monitoring/klaim/bulan/01/tahun/2025/tanggal/31
Deskripsi: Monitor status aplikasi dan klaim
```

## Authentication yang digunakan:

Semua endpoint di atas secara otomatis akan menggunakan:
- **X-cons-id**: consid dari konfigurasi server
- **X-timestamp**: timestamp saat request
- **X-signature**: signature berdasarkan HMAC SHA256
- **user_key**: user key dari konfigurasi server
- **Content-Type**: application/json

## Contoh Test External (Non-BPJS):

```
Nama: HTTPBin Test
URL: https://httpbin.org/get
Deskripsi: Test endpoint eksternal untuk validasi koneksi

Nama: Google API Test
URL: https://www.googleapis.com/gmail/v1/users/me/labels
Deskripsi: Test Google API (akan error karena auth, tapi test konektivitas)

Nama: GitHub API
URL: https://api.github.com/repos/laravel/laravel
Deskripsi: Test GitHub API public endpoint
```

## Tips:
1. **BPJS Endpoints**: Semua URL yang mengandung `bpjs-kesehatan.go.id` otomatis menggunakan BPJS authentication
2. **External Endpoints**: URL lain menggunakan request biasa tanpa authentication tambahan
3. **Timeout**: Disarankan 10-15 detik untuk BPJS endpoints karena bisa lambat
4. **Method**: Kebanyakan BPJS endpoints menggunakan GET, beberapa POST untuk data submission

## Testing:
Gunakan tombol "Test" di dashboard untuk memvalidasi endpoint sebelum menambahkan ke monitoring reguler.
