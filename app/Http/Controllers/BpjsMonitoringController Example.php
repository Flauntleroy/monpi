<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Models\BpjsMonitoringLog;
use App\Models\BpjsMonitoringAlert;
use App\Models\BpjsEndpointConfig;
use App\Helpers\FonnteWhatsapp;

class BpjsMonitoringController extends Controller
{
    // Kredensial BPJS
    private $api_url = 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/';
    private $consid = 'xxxx';
    private $secretkey = 'xxxxx';
    private $user_key = 'xxxxx';

    public function index()
    {
        // Seed endpoint configurations if empty
        $this->seedEndpointConfigs();
        
        return Inertia::render('BpjsMonitoring/Dashboard');
    }

    public function getMonitoringData()
    {
        // Check cache first (refresh every 30 seconds)
        return Cache::remember('bpjs_monitoring_data', 30, function () {
            return $this->performMonitoring();
        });
    }

    private function performMonitoring()
    {
        date_default_timezone_set('UTC');
        $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
        $checkedAt = Carbon::now();

        // Get active endpoint configurations
        $endpointConfigs = BpjsEndpointConfig::active()->get();
        
        if ($endpointConfigs->isEmpty()) {
            $this->seedEndpointConfigs();
            $endpointConfigs = BpjsEndpointConfig::active()->get();
        }

        $results = [];
        $success = 0;
        $error = 0;
        $total_response_time = 0;
        $alerts = [];

        foreach ($endpointConfigs as $config) {
            $start = microtime(true);
            $status = 'error';
            $code = 'ERROR';
            $message = 'Unknown error';
            $responseHeaders = null;
            $errorDetails = null;

            try {
                $signature = base64_encode(hash_hmac('sha256', $this->consid . '&' . $tStamp, $this->secretkey, true));
                
                $headers = [
                    'X-cons-id' => $this->consid,
                    'X-timestamp' => $tStamp,
                    'X-signature' => $signature,
                    'user_key' => $this->user_key,
                    'Content-Type' => 'application/json',
                ];

                // Merge custom headers if any
                if ($config->custom_headers) {
                    $headers = array_merge($headers, $config->custom_headers);
                }

                $response = Http::timeout($config->timeout_seconds)
                    ->withHeaders($headers)
                    ->get($config->url);

                $end = microtime(true);
                $response_time = round(($end - $start) * 1000, 2);
                
                $responseHeaders = $response->headers();
                $json = $response->json();
                $code = $json['metaData']['code'] ?? $response->status();
                $message = $json['metaData']['message'] ?? $response->reason();
                
                if ($code == 200 || $code == '200') {
                    $success++;
                    $status = 'success';
                } else {
                    $error++;
                    $status = 'error';
                    $errorDetails = json_encode([
                        'response_body' => $response->body(),
                        'status_code' => $response->status()
                    ]);
                }

            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $end = microtime(true);
                $response_time = round(($end - $start) * 1000, 2);
                $status = 'timeout';
                $code = 'TIMEOUT';
                $message = 'Connection timeout';
                $errorDetails = $e->getMessage();
                $error++;
            } catch (\Exception $e) {
                $end = microtime(true);
                $response_time = round(($end - $start) * 1000, 2);
                $code = 'ERROR';
                $message = $e->getMessage();
                $errorDetails = $e->getTraceAsString();
                $error++;
            }

            // Log to database
            BpjsMonitoringLog::create([
                'endpoint_name' => $config->name,
                'endpoint_url' => $config->url,
                'response_time' => $response_time,
                'status_code' => (string)$code,
                'status_message' => $message,
                'status' => $status,
                'response_headers' => $responseHeaders,
                'error_details' => $errorDetails,
                'checked_at' => $checkedAt
            ]);

            // Check for alerts
            $this->checkForAlerts($config, $response_time, $status);

            // Determine severity based on response time
            $severity = $config->getResponseTimeSeverity($response_time);

            $results[] = [
                'name' => $config->name,
                'url' => $config->url,
                'response_time' => $response_time,
                'code' => $code,
                'message' => $message,
                'status' => $status,
                'severity' => $severity,
                'description' => $config->description
            ];

            $total_response_time += $response_time;
            usleep(100000); // 0.1 detik delay
        }

        $total = count($results);
        $avg_response_time = $total > 0 ? round($total_response_time / $total, 2) : 0;
        $uptime_percentage = $total > 0 ? round(($success / $total) * 100, 2) : 0;

        // Get active alerts
        $activeAlerts = BpjsMonitoringAlert::active()
            ->orderBy('triggered_at', 'desc')
            ->limit(10)
            ->get();

        // Get recent statistics
        $stats = $this->getRecentStatistics();

        return response()->json([
            'summary' => [
                'total' => $total,
                'success' => $success,
                'error' => $error,
                'avg_response_time' => $avg_response_time,
                'uptime_percentage' => $uptime_percentage,
                'uptime_24h' => BpjsMonitoringLog::getUptimePercentage(null, 24),
                'avg_response_time_24h' => BpjsMonitoringLog::getAverageResponseTime(null, 24)
            ],
            'endpoints' => $results,
            'alerts' => $activeAlerts->map(function ($alert) {
                return [
                    'id' => $alert->id,
                    'endpoint_name' => $alert->endpoint_name,
                    'type' => $alert->alert_type,
                    'message' => $alert->alert_message,
                    'triggered_at' => $alert->triggered_at->format('Y-m-d H:i:s'),
                    'data' => $alert->alert_data
                ];
            }),
            'statistics' => $stats,
            'timestamp' => $checkedAt->format('Y-m-d H:i:s')
        ]);
    }

    private function checkForAlerts($config, $responseTime, $status)
    {
        // Check for consecutive errors
        if ($status !== 'success') {
            $recentLogs = BpjsMonitoringLog::getConsecutiveErrors($config->name, $config->consecutive_error_threshold);
            
            if ($recentLogs->count() >= $config->consecutive_error_threshold) {
                $allErrors = $recentLogs->every(function ($log) {
                    return $log->status !== 'success';
                });

                if ($allErrors) {
                    // Check if we already have an active alert for this
                    $existingAlert = BpjsMonitoringAlert::active()
                        ->forEndpoint($config->name)
                        ->where('alert_type', 'consecutive_errors')
                        ->first();

                    if (!$existingAlert) {
                        BpjsMonitoringAlert::create([
                            'endpoint_name' => $config->name,
                            'alert_type' => 'consecutive_errors',
                            'alert_message' => "Endpoint {$config->name} has failed {$config->consecutive_error_threshold} consecutive times",
                            'alert_data' => [
                                'threshold' => $config->consecutive_error_threshold,
                                'consecutive_failures' => $recentLogs->count()
                            ],
                            'triggered_at' => now()
                        ]);
                    }
                }
            }
        } else {
            // Resolve consecutive error alerts if endpoint is now successful
            BpjsMonitoringAlert::active()
                ->forEndpoint($config->name)
                ->where('alert_type', 'consecutive_errors')
                ->get()
                ->each(function ($alert) {
                    $alert->resolve();
                });
        }

        // Check for response time alerts
        if ($responseTime >= $config->critical_threshold_ms) {
            $existingAlert = BpjsMonitoringAlert::active()
                ->forEndpoint($config->name)
                ->where('alert_type', 'response_time')
                ->first();

            if (!$existingAlert) {
                BpjsMonitoringAlert::create([
                    'endpoint_name' => $config->name,
                    'alert_type' => 'response_time',
                    'alert_message' => "Endpoint {$config->name} response time ({$responseTime}ms) exceeded critical threshold ({$config->critical_threshold_ms}ms)",
                    'alert_data' => [
                        'response_time' => $responseTime,
                        'threshold' => $config->critical_threshold_ms,
                        'severity' => 'critical'
                    ],
                    'triggered_at' => now()
                ]);
            }
        }
    }

    private function getRecentStatistics()
    {
        $last24Hours = BpjsMonitoringLog::getHistoricalData(null, 24, '1 hour');
        
        return [
            'hourly_data' => $last24Hours->map(function ($item) {
                return [
                    'time' => $item->time_bucket,
                    'avg_response_time' => round($item->avg_response_time, 2),
                    'uptime_percentage' => $item->total_checks > 0 ? round(($item->successful_checks / $item->total_checks) * 100, 2) : 0,
                    'total_checks' => $item->total_checks,
                    'successful_checks' => $item->successful_checks
                ];
            }),
            'trends' => [
                'response_time_trend' => $this->calculateTrend($last24Hours, 'avg_response_time'),
                'uptime_trend' => $this->calculateTrend($last24Hours, 'uptime_percentage')
            ]
        ];
    }

    private function calculateTrend($data, $field)
    {
        if ($data->count() < 2) return 'stable';

        $values = $data->pluck($field)->toArray();
        $first_half = array_slice($values, 0, ceil(count($values) / 2));
        $second_half = array_slice($values, floor(count($values) / 2));

        $first_avg = array_sum($first_half) / count($first_half);
        $second_avg = array_sum($second_half) / count($second_half);

        $change_percentage = (($second_avg - $first_avg) / $first_avg) * 100;

        if ($change_percentage > 5) return 'improving';
        if ($change_percentage < -5) return 'declining';
        return 'stable';
    }

    public function getHistoricalData(Request $request)
    {
        $endpointName = $request->query('endpoint');
        $hours = $request->query('hours', 24);
        $interval = $request->query('interval', '1 hour');

        $data = BpjsMonitoringLog::getHistoricalData($endpointName, $hours, $interval);

        return response()->json([
            'data' => $data,
            'endpoint' => $endpointName,
            'period' => "{$hours} hours"
        ]);
    }

    public function getAlerts(Request $request)
    {
        $query = BpjsMonitoringAlert::query();

        if ($request->has('endpoint')) {
            $query->forEndpoint($request->endpoint);
        }

        if ($request->has('active_only') && $request->active_only) {
            $query->active();
        }

        $alerts = $query->orderBy('triggered_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json($alerts);
    }

    public function resolveAlert($alertId)
    {
        $alert = BpjsMonitoringAlert::findOrFail($alertId);
        $alert->resolve();

        return response()->json(['message' => 'Alert resolved successfully']);
    }

    private function seedEndpointConfigs()
    {
        $endpoints = [
            ['name' => 'Diagnosa', 'url' => $this->api_url . 'referensi/diagnosa/A00', 'description' => 'Referensi data diagnosa'],
            ['name' => 'Poli', 'url' => $this->api_url . 'referensi/poli/INT', 'description' => 'Referensi data poli'],
            ['name' => 'Dokter DPJP', 'url' => $this->api_url . 'referensi/dokter/pelayanan/1/tglPelayanan/' . date('Y-m-d') . '/Spesialis/INT', 'description' => 'Referensi dokter DPJP'],
            ['name' => 'Propinsi', 'url' => $this->api_url . 'referensi/propinsi', 'description' => 'Referensi data propinsi'],
            ['name' => 'Kabupaten', 'url' => $this->api_url . 'referensi/kabupaten/propinsi/01', 'description' => 'Referensi data kabupaten'],
            ['name' => 'Kecamatan', 'url' => $this->api_url . 'referensi/kecamatan/kabupaten/0101', 'description' => 'Referensi data kecamatan'],
            ['name' => 'Procedure', 'url' => $this->api_url . 'referensi/procedure/001', 'description' => 'Referensi data prosedur'],
            ['name' => 'Kelas Rawat', 'url' => $this->api_url . 'referensi/kelasrawat', 'description' => 'Referensi kelas rawat'],
            ['name' => 'Spesialistik', 'url' => $this->api_url . 'referensi/spesialistik', 'description' => 'Referensi spesialistik'],
            ['name' => 'Ruang Rawat', 'url' => $this->api_url . 'referensi/ruangrawat', 'description' => 'Referensi ruang rawat'],
            ['name' => 'Cara Keluar', 'url' => $this->api_url . 'referensi/carakeluar', 'description' => 'Referensi cara keluar'],
            ['name' => 'Pasca Pulang', 'url' => $this->api_url . 'referensi/pascapulang', 'description' => 'Referensi pasca pulang'],
            ['name' => 'Rujukan by NoKartu', 'url' => $this->api_url . 'Rujukan/Peserta/0002657364478', 'description' => 'Data rujukan berdasarkan nomor kartu'],
            ['name' => 'Rujukan by TglRujukan', 'url' => $this->api_url . 'Rujukan/List/TglRujukan/' . date('Y-m-d'), 'description' => 'Data rujukan berdasarkan tanggal'],
        ];

        foreach ($endpoints as $endpoint) {
            BpjsEndpointConfig::firstOrCreate(
                ['name' => $endpoint['name']],
                [
                    'url' => $endpoint['url'],
                    'is_active' => true,
                    'timeout_seconds' => 10,
                    'warning_threshold_ms' => 1000.00,
                    'critical_threshold_ms' => 2000.00,
                    'consecutive_error_threshold' => 3,
                    'description' => $endpoint['description']
                ]
            );
        }
    }

    public function testCustomEndpoint(Request $request)
    {
        $request->validate([
            'url' => 'required|url',
            'method' => 'sometimes|string|in:GET,POST,PUT,DELETE,PATCH,PING',
            'timeout' => 'sometimes|integer|min:1|max:60'
        ]);

        $url = $request->input('url');
        $method = $request->input('method', 'GET');
        $timeout = $request->input('timeout', 10);
        
        $start = microtime(true);
        
        try {
            // Check if this is a BPJS endpoint
            $isBpjsEndpoint = $this->isBpjsEndpoint($url);

            if (strtoupper($method) === 'PING') {
                // Perform HEAD ping; fallback to GET if HEAD not allowed
                if ($isBpjsEndpoint) {
                    $headers = $this->getBpjsHeaders();
                    $start = microtime(true);
                    $response = Http::withHeaders($headers)
                        ->timeout($timeout)
                        ->head($url);
                    if ($response->status() === 405) {
                        // Fallback to GET for servers that do not support HEAD
                        $start = microtime(true);
                        $response = Http::withHeaders($headers)
                            ->timeout($timeout)
                            ->get($url);
                    }
                } else {
                    $start = microtime(true);
                    $response = Http::timeout($timeout)->head($url);
                    if ($response->status() === 405) {
                        $start = microtime(true);
                        $response = Http::timeout($timeout)->get($url);
                    }
                }
            } else {
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
            }
            
            $end = microtime(true);
            $responseTime = round(($end - $start) * 1000); // Convert to milliseconds
            
            $severity = $this->getSeverityFromResponseTime($responseTime);
            
            // For BPJS endpoints, check the metaData structure (skip for PING)
            if ($isBpjsEndpoint && strtoupper($method) !== 'PING' && $response->successful()) {
                $json = $response->json();
                $code = $json['metaData']['code'] ?? $response->status();
                $message = $json['metaData']['message'] ?? $response->reason();
                $status = ($code == 200 || $code == '200') ? 'success' : 'error';
                
                // Kirim notifikasi jika code 201 atau 404 (SELALU, untuk BPJS endpoint) dengan cooldown 30 menit
                if (in_array($code, [201, '201', 404, '404'])) {
                    FonnteWhatsapp::sendEndpointAlert("BPJS API", $code, $message, $url, 30);
                }
            } else {
                $code = $response->status();
                $message = $response->reason() ?? 'OK';
                // For PING, treat 2xx-3xx as reachable
                $status = (strtoupper($method) === 'PING')
                    ? (($code >= 200 && $code < 400) ? 'success' : 'error')
                    : ($response->successful() ? 'success' : 'error');
                
                // Kirim notifikasi jika code 201 atau 404 (SELALU, tidak peduli successful atau tidak) dengan cooldown 30 menit
                if (in_array($code, [201, '201', 404, '404'])) {
                    FonnteWhatsapp::sendEndpointAlert("API", $code, $message, $url, 30);
                }
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
            // Kirim notifikasi WhatsApp untuk timeout dengan cooldown 60 menit
            FonnteWhatsapp::sendCriticalAlert("API Timeout", "Connection timeout", $url, 60);
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
            // Kirim notifikasi WhatsApp untuk error critical dengan cooldown 60 menit
            FonnteWhatsapp::sendCriticalAlert("API Critical", $e->getMessage(), $url, 60);
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
