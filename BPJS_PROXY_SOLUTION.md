# SOLUSI BPJS CUSTOM ENDPOINT ERROR

## Masalah
BPJS custom endpoints yang ditambahkan user menunjukkan status "ERROR critical" karena:
1. **CORS Policy**: Browser tidak bisa langsung mengakses BPJS API
2. **Authentication Headers**: BPJS API membutuhkan signature-based authentication yang tidak bisa dilakukan di browser
3. **Direct API Calls**: Browser tidak bisa mengirim header `X-cons-id`, `X-timestamp`, `X-signature`, dan `user_key`

## Solusi yang Diimplementasikan

### 1. Auto-Detection BPJS Endpoints
- **File**: `Dashboard.vue` (addCustomEndpoint function)
- **Logic**: Otomatis mendeteksi URL yang mengandung `bpjs-kesehatan.go.id`
- **Action**: Auto-enable proxy dan tampilkan warning ke user

```typescript
// Auto-detect BPJS endpoints and enable proxy
const isBpjsEndpoint = endpoint.url.includes('bpjs-kesehatan.go.id');
if (isBpjsEndpoint) {
  endpoint.isBpjsEndpoint = true;
  endpoint.useProxy = true;
  // Show warning to user
}
```

### 2. Smart Endpoint Testing
- **File**: `Dashboard.vue` (testCustomEndpoint function)
- **Logic**: Berbeda handling untuk BPJS dan non-BPJS endpoints
- **BPJS**: Via backend proxy dengan proper authentication
- **Non-BPJS**: Direct browser request

```typescript
if (endpoint.isBpjsEndpoint && endpoint.useProxy) {
  // Use backend proxy for BPJS endpoints
  response = await fetch('/bpjs-monitoring/test-custom-endpoint', {
    method: 'POST',
    body: JSON.stringify({
      url: endpoint.url,
      method: endpoint.method || 'GET',
      timeout: endpoint.timeout || 10
    })
  });
} else {
  // Direct browser request for non-BPJS endpoints
  response = await fetch(endpoint.url, {
    method: endpoint.method || 'GET',
    headers: endpoint.headers
  });
}
```

### 3. Backend Proxy Implementation
- **File**: `BpjsMonitoringControllerSimple.php`
- **Method**: `testCustomEndpoint()`
- **Features**:
  - BPJS endpoint detection
  - Proper BPJS authentication headers
  - Error handling
  - Response time calculation

```php
public function testCustomEndpoint(Request $request)
{
    $url = $request->input('url');
    $isBpjsEndpoint = $this->isBpjsEndpoint($url);
    
    if ($isBpjsEndpoint) {
        $headers = $this->getBpjsHeaders(); // Proper BPJS auth
        $response = Http::withHeaders($headers)->timeout($timeout)->get($url);
    } else {
        $response = Http::timeout($timeout)->get($url);
    }
    
    // Return structured response with timing and status
}
```

### 4. BPJS Authentication Headers
- **Credentials**: Menggunakan credentials yang sudah ada di controller
- **Signature**: Generated dengan HMAC-SHA256
- **Headers**: `X-cons-id`, `X-timestamp`, `X-signature`, `user_key`

```php
private function getBpjsHeaders(): array
{
    $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
    $signature = base64_encode(hash_hmac('sha256', $this->consid . '&' . $tStamp, $this->secretkey, true));
    
    return [
        'X-cons-id' => $this->consid,
        'X-timestamp' => $tStamp,
        'X-signature' => $signature,
        'user_key' => $this->user_key,
        'Content-Type' => 'application/json',
    ];
}
```

### 5. Enhanced Error Handling
- **CORS Detection**: Mendeteksi dan memberikan pesan yang jelas
- **Timeout Handling**: Berbeda handling untuk timeout vs network errors
- **User Guidance**: Memberikan saran untuk enable proxy

```typescript
let message = 'Network error';
if (error.name === 'TimeoutError') {
  message = 'Request timeout';
} else if (error.message?.includes('CORS')) {
  message = 'CORS policy blocked (try enabling proxy for BPJS endpoints)';
} else if (error.message?.includes('Failed to fetch')) {
  message = 'Network unreachable or CORS blocked';
}
```

### 6. New Route
- **Route**: `POST /bpjs-monitoring/test-custom-endpoint`
- **Purpose**: Backend proxy untuk testing custom endpoints
- **Public Access**: Tidak perlu authentication untuk testing

## Hasil yang Diharapkan

### Sebelum (ERROR):
```
Status: ERROR critical
Message: Failed to fetch (CORS blocked)
Response Time: 0ms
```

### Sesudah (SUCCESS):
```
Status: SUCCESS
Message: OK  
Response Time: 850ms
Severity: good
Backend proxy: Yes
BPJS Endpoint Detected: ✓
```

## Cara Testing

### 1. Manual Testing
```bash
# Start Laravel server
php artisan serve --port=8000

# Open browser
http://localhost:8000/test-bpjs-proxy.html
```

### 2. Via Dashboard
1. Buka dashboard: `http://localhost:8000/bpjs-monitoring`
2. Klik "Manage Custom Endpoints"
3. Add endpoint: `https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/Peserta/nik/6304151101990001/tglSEP/2025-07-31`
4. System akan auto-detect BPJS dan enable proxy
5. Test endpoint akan sukses dengan proper authentication

## Files Modified

1. **Dashboard.vue**
   - Enhanced CustomEndpoint interface dengan `isBpjsEndpoint` dan `useProxy`
   - Modified `addCustomEndpoint()` dengan auto-detection
   - Updated `testCustomEndpoint()` dengan smart routing

2. **BpjsMonitoringControllerSimple.php**
   - Added `testCustomEndpoint()` method
   - Added BPJS detection dan authentication logic
   - Enhanced error handling

3. **routes/web.php**
   - Added route untuk proxy endpoint

4. **Test Files**
   - `test-bpjs-proxy.html`: Manual testing interface
   - `test-bpjs-logic.php`: Logic validation

## Benefits

1. **User Experience**: BPJS endpoints sekarang bekerja tanpa error
2. **Automatic**: Auto-detection dan auto-enable proxy
3. **Secure**: Proper authentication headers di backend
4. **Flexible**: Masih support non-BPJS endpoints dengan direct calls
5. **Informative**: Clear feedback tentang proxy usage dan BPJS detection

## Kesimpulan

Solusi ini mengatasi masalah CORS dan authentication untuk BPJS endpoints dengan:
- Smart endpoint detection
- Backend proxy dengan proper authentication
- Enhanced user experience
- Backward compatibility untuk non-BPJS endpoints

BPJS custom endpoints sekarang akan menunjukkan status SUCCESS dengan response time yang akurat dan pesan yang informatif.
