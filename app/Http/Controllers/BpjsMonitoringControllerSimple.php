<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Carbon\Carbon;

class BpjsMonitoringControllerSimple extends Controller
{
    // Kredensial BPJS
    private $api_url = 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/';
    private $consid = '17432';
    private $secretkey = '3nK53BBE23';
    private $user_key = '1823bb1d8015aee02180ee12d2af2b2c';

    public function index()
    {
        return Inertia::render('BpjsMonitoring/Dashboard');
    }

    public function getMonitoringData()
    {
        // Check cache first (refresh every 30 seconds)
        return Cache::remember('bpjs_monitoring_data_simple', 30, function () {
            return $this->performMonitoring();
        });
    }

    private function performMonitoring()
    {
        date_default_timezone_set('UTC');
        $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
        $checkedAt = Carbon::now();

        $endpoints = [
            ['name' => 'Diagnosa', 'url' => $this->api_url . 'referensi/diagnosa/A00', 'description' => 'Referensi data diagnosa'],
            ['name' => 'Poli', 'url' => $this->api_url . 'referensi/poli/INT', 'description' => 'Referensi data poli'],
            ['name' => 'Faskes', 'url' => $this->api_url . 'referensi/faskes/0101R001/1', 'description' => 'Referensi data fasilitas kesehatan'],
            ['name' => 'Dokter DPJP', 'url' => $this->api_url . 'referensi/dokter/pelayanan/1/tglPelayanan/' . date('Y-m-d') . '/Spesialis/INT', 'description' => 'Referensi dokter DPJP'],
            ['name' => 'Propinsi', 'url' => $this->api_url . 'referensi/propinsi', 'description' => 'Referensi data propinsi'],
            ['name' => 'Kabupaten', 'url' => $this->api_url . 'referensi/kabupaten/propinsi/01', 'description' => 'Referensi data kabupaten'],
            ['name' => 'Kecamatan', 'url' => $this->api_url . 'referensi/kecamatan/kabupaten/0101', 'description' => 'Referensi data kecamatan'],
            ['name' => 'Procedure', 'url' => $this->api_url . 'referensi/procedure/001', 'description' => 'Referensi data prosedur'],
            ['name' => 'Kelas Rawat', 'url' => $this->api_url . 'referensi/kelasrawat', 'description' => 'Referensi kelas rawat'],
            ['name' => 'Dokter', 'url' => $this->api_url . 'referensi/dokter/A', 'description' => 'Referensi data dokter'],
        ];

        $results = [];
        $success = 0;
        $error = 0;
        $total_response_time = 0;

        foreach ($endpoints as $endpoint) {
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
            $severity = 'excellent';
            if ($response_time >= 2000) {
                $severity = 'critical';
            } elseif ($response_time >= 1000) {
                $severity = 'slow';
            } elseif ($response_time >= 500) {
                $severity = 'good';
            }

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

        // Generate some dummy historical data for demo
        $hourly_data = [];
        for ($i = 23; $i >= 0; $i--) {
            $time = Carbon::now()->subHours($i);
            $hourly_data[] = [
                'time' => $time->toISOString(),
                'avg_response_time' => $avg_response_time + rand(-200, 200),
                'uptime_percentage' => min(100, max(0, $uptime_percentage + rand(-10, 10))),
                'total_checks' => 10,
                'successful_checks' => rand(7, 10)
            ];
        }

        return response()->json([
            'summary' => [
                'total' => $total,
                'success' => $success,
                'error' => $error,
                'avg_response_time' => $avg_response_time,
                'uptime_percentage' => $uptime_percentage,
                'uptime_24h' => $uptime_percentage + rand(-5, 5),
                'avg_response_time_24h' => $avg_response_time + rand(-100, 100)
            ],
            'endpoints' => $results,
            'alerts' => [], // No alerts for simple version
            'statistics' => [
                'hourly_data' => $hourly_data,
                'trends' => [
                    'response_time_trend' => 'stable',
                    'uptime_trend' => 'improving'
                ]
            ],
            'timestamp' => $checkedAt->format('Y-m-d H:i:s')
        ]);
    }

    public function getHistoricalData(Request $request)
    {
        // Return dummy historical data
        return response()->json([
            'data' => [],
            'endpoint' => $request->query('endpoint'),
            'period' => $request->query('hours', 24) . ' hours'
        ]);
    }

    public function getAlerts(Request $request)
    {
        // Return empty alerts for simple version
        return response()->json([
            'data' => [],
            'current_page' => 1,
            'total' => 0
        ]);
    }

    public function resolveAlert($alertId)
    {
        return response()->json(['message' => 'Alert resolved successfully']);
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
            
            $severity = $this->getSeverityFromResponseTime($responseTime);
            
            // For BPJS endpoints, check the metaData structure
            if ($isBpjsEndpoint && $response->successful()) {
                $json = $response->json();
                $code = $json['metaData']['code'] ?? $response->status();
                $message = $json['metaData']['message'] ?? $response->reason();
                $status = ($code == 200 || $code == '200') ? 'success' : 'error';
            } else {
                $code = $response->status();
                $message = $response->reason() ?? 'OK';
                $status = $response->successful() ? 'success' : 'error';
            }
            
            return response()->json([
                'response_time' => $responseTime,
                'code' => $code,
                'message' => $message,
                'status' => $status,
                'severity' => $severity,
                'body_preview' => $this->getBodyPreview($response->body()),
                'is_bpjs' => $isBpjsEndpoint
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

    private function getSeverityFromResponseTime(int $responseTime): string
    {
        if ($responseTime >= 2000) return 'critical';
        if ($responseTime >= 1000) return 'slow';  
        if ($responseTime >= 500) return 'good';
        return 'excellent';
    }

    private function getBodyPreview(string $body): string
    {
        // Return first 200 characters of response body for preview
        return strlen($body) > 200 ? substr($body, 0, 200) . '...' : $body;
    }
}
