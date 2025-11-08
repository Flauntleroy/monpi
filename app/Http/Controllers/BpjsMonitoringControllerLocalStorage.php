<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Carbon\Carbon;

class BpjsMonitoringControllerLocalStorage extends Controller
{
    // Kredensial BPJS - Updated with correct values
    private $api_url = 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/';
    private $consid = '21095';
    private $secretkey = '468C5DFE8E9';
    private $user_key = 'bdc202481c6658e7c8b25ac7ee65c7dc';

    public function index()
    {
        return Inertia::render('BpjsMonitoring/Dashboard');
    }

    public function getMonitoringData()
    {
        return $this->performMonitoring();
    }

    private function performMonitoring()
    {
        date_default_timezone_set('UTC');
        $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
        $checkedAt = Carbon::now();

        // Default endpoints untuk monitoring - hanya yang valid
        $defaultEndpoints = [
            ['name' => 'Diagnosa', 'url' => $this->api_url . 'referensi/diagnosa/A00', 'description' => 'Referensi data diagnosa'],
            ['name' => 'Poli', 'url' => $this->api_url . 'referensi/poli/INT', 'description' => 'Referensi data poli'],
            ['name' => 'Faskes', 'url' => $this->api_url . 'referensi/faskes/1702R002/2', 'description' => 'Referensi data fasilitas kesehatan'],
            ['name' => 'Propinsi', 'url' => $this->api_url . 'referensi/propinsi', 'description' => 'Referensi data propinsi'],
            ['name' => 'Kabupaten', 'url' => $this->api_url . 'referensi/kabupaten/propinsi/01', 'description' => 'Referensi data kabupaten'],
            ['name' => 'Kecamatan', 'url' => $this->api_url . 'referensi/kecamatan/kabupaten/0101', 'description' => 'Referensi data kecamatan'],
            ['name' => 'Kelas Rawat', 'url' => $this->api_url . 'referensi/kelasrawat', 'description' => 'Referensi kelas rawat'],
            ['name' => 'Spesialistik', 'url' => $this->api_url . 'referensi/spesialistik', 'description' => 'Referensi spesialistik'],
            ['name' => 'Ruang Rawat', 'url' => $this->api_url . 'referensi/ruangrawat', 'description' => 'Referensi ruang rawat'],
            ['name' => 'Cara Keluar', 'url' => $this->api_url . 'referensi/carakeluar', 'description' => 'Referensi cara keluar'],
            ['name' => 'Pasca Pulang', 'url' => $this->api_url . 'referensi/pascapulang', 'description' => 'Referensi pasca pulang'],
            ['name' => 'Rujukan by NoRujukan', 'url' => $this->api_url . 'Rujukan/170205010525Y000103', 'description' => 'Data rujukan berdasarkan nomor rujukan'],
            ['name' => 'Rujukan by NoKartu', 'url' => $this->api_url . 'Rujukan/Peserta/0002657364478', 'description' => 'Data rujukan berdasarkan nomor kartu'],
            ['name' => 'Rujukan by TglRujukan', 'url' => $this->api_url . 'Rujukan/List/TglRujukan/' . date('Y-m-d'), 'description' => 'Data rujukan berdasarkan tanggal'],
            // Removed timestamp endpoint - not available (404)
        ];

        $results = [];
        $success = 0;
        $error = 0;
        $total_response_time = 0;

        foreach ($defaultEndpoints as $endpoint) {
            $start = microtime(true);
            $status = 'error';
            $code = 'ERROR';
            $message = 'Unknown error';

            try {
                $signature = base64_encode(hash_hmac('sha256', $this->consid . '&' . $tStamp, $this->secretkey, true));
                
                $headers = [
                    'X-cons-id' => $this->consid,
                    'X-timestamp' => $tStamp,
                    'X-signature' => $signature,
                    'user_key' => $this->user_key,
                    'Content-Type' => 'application/json',
                ];

                $response = Http::timeout(10)
                    ->withHeaders($headers)
                    ->get($endpoint['url']);

                $end = microtime(true);
                $response_time = round(($end - $start) * 1000, 2);
                
                $json = $response->json();
                $code = $json['metaData']['code'] ?? $response->status();
                $message = $json['metaData']['message'] ?? $response->reason();
                
                if ($code == 200 || $code == '200') {
                    $success++;
                    $status = 'success';
                } else {
                    $error++;
                    $status = 'error';
                }

            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $end = microtime(true);
                $response_time = round(($end - $start) * 1000, 2);
                $status = 'timeout';
                $code = 'TIMEOUT';
                $message = 'Connection timeout';
                $error++;
            } catch (\Exception $e) {
                $end = microtime(true);
                $response_time = round(($end - $start) * 1000, 2);
                $code = 'ERROR';
                $message = $e->getMessage();
                $error++;
            }

            // Determine severity based on response time
            $severity = $this->getResponseTimeSeverity($response_time);

            $results[] = [
                'name' => $endpoint['name'],
                'url' => $endpoint['url'],
                'response_time' => $response_time,
                'code' => $code,
                'message' => $message,
                'status' => $status,
                'severity' => $severity,
                'description' => $endpoint['description']
            ];

            $total_response_time += $response_time;
            usleep(100000); // 0.1 detik delay
        }

        $total = count($results);
        $avg_response_time = $total > 0 ? round($total_response_time / $total, 2) : 0;
        $uptime_percentage = $total > 0 ? round(($success / $total) * 100, 2) : 0;

        // Perform network diagnostics
        $networkDiagnostics = $this->performNetworkDiagnostics();

        return response()->json([
            'summary' => [
                'total' => $total,
                'success' => $success,
                'error' => $error,
                'avg_response_time' => $avg_response_time,
                'uptime_percentage' => $uptime_percentage,
                'uptime_24h' => $uptime_percentage, // Same as current since no historical data
                'avg_response_time_24h' => $avg_response_time
            ],
            'endpoints' => $results,
            'alerts' => [], // No alerts without database
            'statistics' => [
                'hourly_data' => [],
                'trends' => [
                    'response_time_trend' => 'stable',
                    'uptime_trend' => 'stable'
                ]
            ],
            'network_diagnostics' => $networkDiagnostics,
            'timestamp' => $checkedAt->format('Y-m-d H:i:s')
        ]);
    }

    private function getResponseTimeSeverity($responseTime): string
    {
        if ($responseTime >= 2000) return 'critical';
        if ($responseTime >= 1000) return 'slow';  
        if ($responseTime >= 500) return 'good';
        return 'excellent';
    }

    /**
     * Perform comprehensive network diagnostics to differentiate between
     * local network issues vs BPJS server issues
     */
    private function performNetworkDiagnostics(): array
    {
        $diagnostics = [
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'local_connectivity' => [],
            'dns_resolution' => [],
            'external_connectivity' => [],
            'bpjs_infrastructure' => [],
            'analysis' => []
        ];

        // 1. Test local connectivity (ping external reliable servers)
        $diagnostics['local_connectivity'] = $this->testLocalConnectivity();
        
        // 2. Test DNS resolution
        $diagnostics['dns_resolution'] = $this->testDnsResolution();
        
        // 3. Test external connectivity (different reliable APIs)
        $diagnostics['external_connectivity'] = $this->testExternalConnectivity();
        
        // 4. Test BPJS infrastructure specifically
        $diagnostics['bpjs_infrastructure'] = $this->testBpjsInfrastructure();
        
        // 5. Analyze results and determine root cause
        $diagnostics['analysis'] = $this->analyzeDiagnostics($diagnostics);
        
        return $diagnostics;
    }

    private function testLocalConnectivity(): array
    {
        $tests = [];
        
        // Test ping to reliable external servers
        $reliableHosts = [
            'google.com' => '8.8.8.8',
            'cloudflare.com' => '1.1.1.1',
            'opendns.com' => '208.67.222.222'
        ];

        foreach ($reliableHosts as $hostname => $ip) {
            $start = microtime(true);
            try {
                // Test HTTP connectivity instead of ping (since ping might be blocked)
                $response = Http::timeout(5)->get("https://{$hostname}");
                $end = microtime(true);
                $responseTime = round(($end - $start) * 1000);
                
                $tests[] = [
                    'host' => $hostname,
                    'ip' => $ip,
                    'status' => $response->successful() ? 'success' : 'error',
                    'response_time' => $responseTime,
                    'http_code' => $response->status()
                ];
            } catch (\Exception $e) {
                $end = microtime(true);
                $responseTime = round(($end - $start) * 1000);
                
                $tests[] = [
                    'host' => $hostname,
                    'ip' => $ip,
                    'status' => 'error',
                    'response_time' => $responseTime,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'tests' => $tests,
            'overall_status' => $this->calculateOverallStatus($tests)
        ];
    }

    private function testDnsResolution(): array
    {
        $tests = [];
        $domains = [
            'apijkn.bpjs-kesehatan.go.id',
            'bpjs-kesehatan.go.id',
            'google.com',
            'cloudflare.com'
        ];

        foreach ($domains as $domain) {
            $start = microtime(true);
            try {
                $ip = gethostbyname($domain);
                $end = microtime(true);
                $responseTime = round(($end - $start) * 1000);
                
                $tests[] = [
                    'domain' => $domain,
                    'resolved_ip' => $ip,
                    'status' => ($ip !== $domain) ? 'success' : 'error',
                    'response_time' => $responseTime
                ];
            } catch (\Exception $e) {
                $end = microtime(true);
                $responseTime = round(($end - $start) * 1000);
                
                $tests[] = [
                    'domain' => $domain,
                    'status' => 'error',
                    'response_time' => $responseTime,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'tests' => $tests,
            'overall_status' => $this->calculateOverallStatus($tests)
        ];
    }

    private function testExternalConnectivity(): array
    {
        $tests = [];
        
        // Test various external APIs to confirm internet connectivity
        $externalApis = [
            ['name' => 'HTTPBin', 'url' => 'https://httpbin.org/get'],
            ['name' => 'JSONPlaceholder', 'url' => 'https://jsonplaceholder.typicode.com/posts/1'],
            ['name' => 'GitHub API', 'url' => 'https://api.github.com'],
        ];

        foreach ($externalApis as $api) {
            $start = microtime(true);
            try {
                $response = Http::timeout(10)->get($api['url']);
                $end = microtime(true);
                $responseTime = round(($end - $start) * 1000);
                
                $tests[] = [
                    'name' => $api['name'],
                    'url' => $api['url'],
                    'status' => $response->successful() ? 'success' : 'error',
                    'response_time' => $responseTime,
                    'http_code' => $response->status()
                ];
            } catch (\Exception $e) {
                $end = microtime(true);
                $responseTime = round(($end - $start) * 1000);
                
                $tests[] = [
                    'name' => $api['name'],
                    'url' => $api['url'],
                    'status' => 'error',
                    'response_time' => $responseTime,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'tests' => $tests,
            'overall_status' => $this->calculateOverallStatus($tests)
        ];
    }

    private function testBpjsInfrastructure(): array
    {
        $tests = [];
        
        // Test BPJS infrastructure at different levels
        $bpjsTests = [
            ['name' => 'BPJS Main Website', 'url' => 'https://bpjs-kesehatan.go.id', 'type' => 'website'],
            ['name' => 'BPJS API Base', 'url' => 'https://apijkn.bpjs-kesehatan.go.id', 'type' => 'api_base'],
            ['name' => 'BPJS Vclaim Health Check', 'url' => $this->api_url . 'referensi/propinsi', 'type' => 'health_check'],
        ];

        foreach ($bpjsTests as $test) {
            $start = microtime(true);
            try {
                if ($test['type'] === 'health_check') {
                    // For health check, use BPJS headers
                    $headers = $this->getBpjsHeaders();
                    $response = Http::withHeaders($headers)->timeout(15)->get($test['url']);
                } else {
                    // For website and base API, simple request
                    $response = Http::timeout(15)->get($test['url']);
                }
                
                $end = microtime(true);
                $responseTime = round(($end - $start) * 1000);
                
                $tests[] = [
                    'name' => $test['name'],
                    'url' => $test['url'],
                    'type' => $test['type'],
                    'status' => $response->successful() ? 'success' : 'error',
                    'response_time' => $responseTime,
                    'http_code' => $response->status(),
                    'response_size' => strlen($response->body())
                ];
            } catch (\Exception $e) {
                $end = microtime(true);
                $responseTime = round(($end - $start) * 1000);
                
                $tests[] = [
                    'name' => $test['name'],
                    'url' => $test['url'],
                    'type' => $test['type'],
                    'status' => 'error',
                    'response_time' => $responseTime,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'tests' => $tests,
            'overall_status' => $this->calculateOverallStatus($tests)
        ];
    }

    private function calculateOverallStatus(array $tests): string
    {
        $successCount = 0;
        $totalCount = count($tests);
        
        foreach ($tests as $test) {
            if ($test['status'] === 'success') {
                $successCount++;
            }
        }
        
        $successRate = $totalCount > 0 ? ($successCount / $totalCount) * 100 : 0;
        
        if ($successRate >= 80) return 'good';
        if ($successRate >= 50) return 'warning';
        return 'critical';
    }

    private function analyzeDiagnostics(array $diagnostics): array
    {
        $analysis = [
            'root_cause' => 'unknown',
            'confidence' => 0,
            'recommendations' => [],
            'summary' => ''
        ];

        $localStatus = $diagnostics['local_connectivity']['overall_status'];
        $dnsStatus = $diagnostics['dns_resolution']['overall_status'];
        $externalStatus = $diagnostics['external_connectivity']['overall_status'];
        $bpjsStatus = $diagnostics['bpjs_infrastructure']['overall_status'];

        // Analyze patterns to determine root cause
        if ($localStatus === 'critical' || $dnsStatus === 'critical') {
            $analysis['root_cause'] = 'local_network_issue';
            $analysis['confidence'] = 90;
            $analysis['summary'] = 'Masalah koneksi internet lokal atau DNS. BPJS kemungkinan normal.';
            $analysis['recommendations'] = [
                'Periksa koneksi internet Anda',
                'Coba restart modem/router',
                'Ganti DNS ke 8.8.8.8 atau 1.1.1.1',
                'Hubungi ISP jika masalah berlanjut'
            ];
        } elseif ($externalStatus === 'good' && $bpjsStatus === 'critical') {
            $analysis['root_cause'] = 'bpjs_server_issue';
            $analysis['confidence'] = 85;
            $analysis['summary'] = 'Server BPJS mengalami gangguan. Internet lokal normal.';
            $analysis['recommendations'] = [
                'Tunggu hingga server BPJS pulih',
                'Cek pengumuman resmi BPJS',
                'Coba lagi dalam beberapa menit',
                'Hubungi support BPJS jika urgent'
            ];
        } elseif ($externalStatus === 'warning' && $bpjsStatus === 'warning') {
            $analysis['root_cause'] = 'mixed_connectivity_issue';
            $analysis['confidence'] = 70;
            $analysis['summary'] = 'Masalah konektivitas campuran - internet lambat dan BPJS tidak optimal.';
            $analysis['recommendations'] = [
                'Periksa kecepatan internet',
                'Coba dari jaringan yang berbeda',
                'Monitor selama beberapa menit',
                'Kemungkinan masalah regional'
            ];
        } elseif ($localStatus === 'good' && $externalStatus === 'good' && $bpjsStatus === 'good') {
            $analysis['root_cause'] = 'all_systems_normal';
            $analysis['confidence'] = 95;
            $analysis['summary'] = 'Semua sistem normal. Jika ada masalah BPJS, kemungkinan temporary.';
            $analysis['recommendations'] = [
                'Sistem berjalan normal',
                'Lanjutkan monitoring',
                'Jika ada error, coba refresh'
            ];
        } else {
            $analysis['root_cause'] = 'intermittent_issue';
            $analysis['confidence'] = 60;
            $analysis['summary'] = 'Masalah intermittent terdeteksi. Perlu monitoring lebih lanjut.';
            $analysis['recommendations'] = [
                'Monitor selama 5-10 menit',
                'Catat pola error yang terjadi',
                'Coba refresh beberapa kali',
                'Periksa log error untuk detail'
            ];
        }

        return $analysis;
    }

    private function getBpjsHeaders(): array
    {
        date_default_timezone_set('UTC');
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

    public function testCustomEndpoint(Request $request)
    {
        $request->validate([
            'url' => 'required|url',
            'method' => 'sometimes|string|in:GET,POST,PUT,DELETE,PATCH',
            'timeout' => 'sometimes|integer|min:1|max:60'
        ]);

        $url = $request->input('url');
        $method = $request->input('method', 'GET');
        $timeout = $request->input('timeout', 10);
        
        $start = microtime(true);
        
        try {
            // Check if this is a BPJS endpoint
            $isBpjsEndpoint = $this->isBpjsEndpoint($url);
            
            if ($isBpjsEndpoint) {
                // For BPJS endpoints, use proper authentication headers
                $headers = $this->getBpjsHeaders();
                
                $response = Http::withHeaders($headers)
                    ->timeout($timeout)
                    ->get($url);
            } else {
                // For other endpoints, simple request
                $response = Http::timeout($timeout)->get($url);
            }
            
            $end = microtime(true);
            $responseTime = round(($end - $start) * 1000); // Convert to milliseconds
            
            $severity = $this->getResponseTimeSeverity($responseTime);
            
            // For BPJS endpoints, check the metaData structure
            if ($isBpjsEndpoint && $response->successful()) {
                $json = $response->json();
                $code = $json['metaData']['code'] ?? $response->status();
                $message = $json['metaData']['message'] ?? $response->reason();
                
                // Handle BPJS specific error codes
                if ($code == 404 || $code == '404') {
                    $status = 'not_found';
                    $message = 'BPJS Endpoint not found (404): ' . $message;
                } elseif ($code == 401 || $code == '401') {
                    $status = 'auth_error'; 
                    $message = 'BPJS Authentication issue: ' . $message;
                } elseif ($code == 200 || $code == '200') {
                    $status = 'success';
                } else {
                    $status = 'error';
                    $message = 'BPJS API error (' . $code . '): ' . $message;
                }
            } else {
                $code = $response->status();
                $message = $response->reason() ?? 'OK';
                
                // Handle HTTP status codes
                if ($code == 404) {
                    $status = 'not_found';
                    $message = 'Endpoint not found (404). This URL may no longer be available.';
                } elseif ($code >= 400 && $code < 500) {
                    $status = 'client_error';
                    $message = 'Client error (' . $code . '): ' . $message;
                } elseif ($code >= 500) {
                    $status = 'server_error';
                    $message = 'Server error (' . $code . '): ' . $message;
                } else {
                    $status = $response->successful() ? 'success' : 'error';
                }
            }
            
            return response()->json([
                'response_time' => $responseTime,
                'code' => $code,
                'message' => $message,
                'status' => $status,
                'severity' => $severity,
                'body_preview' => $this->getBodyPreview($response->body()),
                'is_bpjs' => $isBpjsEndpoint,
                'http_status' => $response->status(),
                'help' => $status === 'not_found' ? 
                    'This endpoint returns 404. Consider removing it from your custom endpoints.' : 
                    ($status === 'auth_error' ? 'Check BPJS API credentials in config/services.php' : null)
            ]);
            
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $end = microtime(true);
            $responseTime = round(($end - $start) * 1000);
            
            return response()->json([
                'response_time' => $responseTime,
                'code' => 'TIMEOUT',
                'message' => 'Connection timeout',
                'status' => 'timeout',
                'severity' => 'critical',
                'error_type' => 'connection_timeout'
            ], 500);
            
        } catch (\Exception $e) {
            $end = microtime(true);
            $responseTime = round(($end - $start) * 1000);
            
            return response()->json([
                'response_time' => $responseTime,
                'code' => 'ERROR',
                'message' => $e->getMessage(),
                'status' => 'error',
                'severity' => 'critical',
                'error_type' => 'general_exception'
            ], 500);
        }
    }

    private function isBpjsEndpoint(string $url): bool
    {
        return strpos($url, 'bpjs-kesehatan.go.id') !== false;
    }

    private function getBodyPreview(string $body): string
    {
        // Return first 200 characters of response body for preview
        return strlen($body) > 200 ? substr($body, 0, 200) . '...' : $body;
    }
}
