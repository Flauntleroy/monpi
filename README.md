# BPJS API Monitoring Dashboard

Dashboard monitoring modern dan minimalis untuk memantau konektivitas API BPJS secara real-time.

## 📚 Documentation
- Deployment Guide: `docs/DEPLOYMENT.md`
- Feature: Custom Endpoints: `docs/CUSTOM_ENDPOINTS_FEATURE.md`
- Feature: Network Diagnostic: `docs/NETWORK_DIAGNOSTIC.md`
- Reference: Advanced Features: `docs/ADVANCED_FEATURES.md`
- Reference: Endpoint Examples: `docs/BPJS_ENDPOINT_EXAMPLES.md`
- Reference: BPJS Proxy Solution: `docs/BPJS_PROXY_SOLUTION.md`
- Reference: CSRF Auth Fix: `docs/CSRF_BPJS_AUTH_FIX.md`
- Reference: LocalStorage System: `docs/LOCALSTORAGE_SYSTEM_DOCUMENTATION.md`
- Archive Index: `docs/archive/README.md`

### 1. **Dashboard Overview**

### 2. **Real-time Monitoring**

### 3. **Response Time Chart**

### 4. **Endpoint Status Table**


Kredensial BPJS (dapat diubah di `BpjsMonitoringController.php`):
```php
private $api_url = 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/';
private $consid = 'xxxxx';
private $secretkey = 'xxxxx';
private $user_key = 'xxxxx';
```
