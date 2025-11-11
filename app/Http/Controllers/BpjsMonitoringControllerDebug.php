<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Helpers\FonnteWhatsapp;

class BpjsMonitoringControllerDebug extends Controller
{
    // Kredensial BPJS
    private $api_url = 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/';
    private $consid;
    private $secretkey;
    private $user_key;

    public function __construct()
    {
        $this->consid = config('bpjs.consid');
        $this->secretkey = config('bpjs.secretkey');
        $this->user_key = config('bpjs.user_key');
    }

    public function index()
    {
        return Inertia::render('Dashboard');
    }

    public function getMonitoringData()
    {
        try {
            // Endpoint list di-hardcode sesuai screenshot (kecuali item merah)
            $endpoints = [
                // Baseline public endpoints
                ['name' => 'Google DNS', 'url' => 'https://dns.google/resolve?name=google.com&type=A', 'description' => 'Google Public DNS API', 'type' => 'baseline'],
                ['name' => 'Cloudflare DNS', 'url' => 'https://cloudflare-dns.com/dns-query?name=cloudflare.com&type=A', 'description' => 'Cloudflare DNS over HTTPS', 'type' => 'baseline'],


                // Variasi non-prefix "BPJS" sesuai list
                ['name' => 'Cara Keluar', 'url' => $this->api_url . 'referensi/carakeluar', 'description' => 'Referensi cara keluar', 'type' => 'bpjs'],
                ['name' => 'Diagnosa', 'url' => $this->api_url . 'referensi/diagnosa/A02', 'description' => 'Referensi data diagnosa', 'type' => 'bpjs'],
                // (Merah diabaikan): Dokter
                ['name' => 'Dokter DPJP', 'url' => $this->api_url . 'referensi/dokter/pelayanan/1/tglPelayanan/' . date('Y-m-d') . '/Spesialis/INT', 'description' => 'Referensi dokter DPJP', 'type' => 'bpjs'],
                // (Merah diabaikan): Faskes
                ['name' => 'Kabupaten', 'url' => $this->api_url . 'referensi/kabupaten/propinsi/01', 'description' => 'Referensi data kabupaten', 'type' => 'bpjs'],
                ['name' => 'Kecamatan', 'url' => $this->api_url . 'referensi/kecamatan/kabupaten/0101', 'description' => 'Referensi data kecamatan', 'type' => 'bpjs'],
                ['name' => 'Kelas Rawat', 'url' => $this->api_url . 'referensi/kelasrawat', 'description' => 'Referensi kelas rawat', 'type' => 'bpjs'],
                ['name' => 'Pasca Pulang', 'url' => $this->api_url . 'referensi/pascapulang', 'description' => 'Referensi pasca pulang', 'type' => 'bpjs'],
                ['name' => 'Poli', 'url' => $this->api_url . 'referensi/poli/INT', 'description' => 'Referensi data poli', 'type' => 'bpjs'],
                ['name' => 'Procedure', 'url' => $this->api_url . 'referensi/procedure/001', 'description' => 'Referensi data prosedur', 'type' => 'bpjs'],
                ['name' => 'Propinsi', 'url' => $this->api_url . 'referensi/propinsi', 'description' => 'Referensi data propinsi', 'type' => 'bpjs'],
                ['name' => 'Ruang Rawat', 'url' => $this->api_url . 'referensi/ruangrawat', 'description' => 'Referensi ruang rawat', 'type' => 'bpjs'],
                ['name' => 'Rujukan by NoKartu', 'url' => $this->api_url . 'Rujukan/Peserta/0002657364478', 'description' => 'Data rujukan berdasarkan nomor kartu', 'type' => 'bpjs'],
                // (Merah diabaikan): Rujukan by NoRujukan
                ['name' => 'Rujukan by TglRujukan', 'url' => $this->api_url . 'Rujukan/List/TglRujukan/' . date('Y-m-d'), 'description' => 'Data rujukan berdasarkan tanggal', 'type' => 'bpjs'],
                ['name' => 'Spesialistik', 'url' => $this->api_url . 'referensi/spesialistik', 'description' => 'Referensi spesialistik', 'type' => 'bpjs']
            ];

            $results = [];
            $success = 0;
            $error = 0;
            $total_response_time = 0;
            $bpjs_success = 0;
            $bpjs_error = 0;
            $baseline_success = 0;
            $baseline_error = 0;

            foreach ($endpoints as $endpoint) {
                $start = microtime(true);
                
                try {
                    // Cek apakah ini BPJS endpoint atau baseline
                    $isBpjsEndpoint = ($endpoint['type'] === 'bpjs');
                    
                    if ($isBpjsEndpoint) {
                        // Untuk BPJS endpoints, gunakan headers authentication
                        $headers = $this->getBpjsHeaders();
                        $response = Http::timeout(10)
                            ->withHeaders($headers)
                            ->get($endpoint['url']);
                    } else {
                        // Untuk baseline endpoints, gunakan header yang sesuai per layanan
                        $headers = [
                            'User-Agent' => 'BPJS-Monitoring/1.0',
                        ];

                        $endpointUrl = $endpoint['url'];

                        if (strpos($endpointUrl, 'cloudflare-dns.com/dns-query') !== false) {
                            // Cloudflare DoH membutuhkan Accept: application/dns-json
                            $headers['Accept'] = 'application/dns-json';
                            // Tambahkan ct=application/dns-json untuk kompatibilitas luas
                            if (strpos($endpointUrl, 'ct=') === false) {
                                $endpointUrl .= (strpos($endpointUrl, '?') !== false ? '&' : '?') . 'ct=application/dns-json';
                            }
                        } elseif (strpos($endpointUrl, 'api.github.com') !== false) {
                            // GitHub API sebaiknya menggunakan header berikut
                            $headers['Accept'] = 'application/vnd.github+json';
                            $headers['X-GitHub-Api-Version'] = '2022-11-28';
                            // Gunakan token jika tersedia untuk menghindari rate limit 403
                            $githubToken = env('GITHUB_TOKEN');
                            if (!empty($githubToken)) {
                                $headers['Authorization'] = 'Bearer ' . $githubToken;
                            }
                        } else {
                            $headers['Accept'] = 'application/json';
                        }

                        $response = Http::timeout(10)
                            ->withHeaders($headers)
                            ->get($endpointUrl);
                    }

                    $end = microtime(true);
                    $response_time = round(($end - $start) * 1000, 2);
                    
                    if ($response->successful()) {
                        if ($isBpjsEndpoint) {
                            // Parse BPJS response
                            $json = $response->json();
                            $code = $json['metaData']['code'] ?? $response->status();
                            $message = $json['metaData']['message'] ?? $response->reason();
                        } else {
                            // Parse baseline response
                            $code = $response->status();
                            $message = $response->reason() ?? 'OK';
                        }
                        
                        if ($code == 200 || $code == '200') {
                            $success++;
                            $status = 'success';
                            
                            // Update counter berdasarkan type
                            if ($isBpjsEndpoint) {
                                $bpjs_success++;
                            } else {
                                $baseline_success++;
                            }
                        } else {
                            $error++;
                            $status = 'error';
                            
                            // Update counter berdasarkan type
                            if ($isBpjsEndpoint) {
                                $bpjs_error++;
                            } else {
                                $baseline_error++;
                            }
                            
                            // Kirim notifikasi WhatsApp hanya untuk BPJS endpoints error
                            if ($isBpjsEndpoint && in_array($code, [201, '201', 404, '404'])) {
                                FonnteWhatsapp::sendEndpointAlert($endpoint['name'], $code, $message, $endpoint['url'], 30);
                            }
                            
                            // Untuk baseline endpoints error, kirim alert khusus (ini menandakan masalah internet)
                            if (!$isBpjsEndpoint && in_array($code, [404, '404', 500, '500'])) {
                                FonnteWhatsapp::sendCriticalAlert("Internet/Baseline", "Baseline endpoint failed: " . $endpoint['name'] . " - " . $message, $endpoint['url'], 60);
                            }
                        }
                    } else {
                        $error++;
                        $status = 'error';
                        $code = $response->status();
                        $message = $response->reason();
                        
                        // Update counter berdasarkan type
                        if ($isBpjsEndpoint) {
                            $bpjs_error++;
                        } else {
                            $baseline_error++;
                        }
                        
                        // Kirim notifikasi berdasarkan type endpoint
                        if ($isBpjsEndpoint && in_array($code, [201, '201', 404, '404'])) {
                            FonnteWhatsapp::sendEndpointAlert($endpoint['name'], $code, $message, $endpoint['url'], 30);
                        } elseif (!$isBpjsEndpoint) {
                            FonnteWhatsapp::sendCriticalAlert("Internet/Baseline", "Baseline endpoint failed: " . $endpoint['name'] . " - code: $code", $endpoint['url'], 60);
                        }
                    }

                } catch (\Exception $e) {
                    $end = microtime(true);
                    $response_time = round(($end - $start) * 1000, 2);
                    $code = 'ERROR';
                    $message = $e->getMessage();
                    $status = 'error';
                    $error++;
                    
                    // Update counter berdasarkan type
                    $isBpjsEndpoint = ($endpoint['type'] === 'bpjs');
                    if ($isBpjsEndpoint) {
                        $bpjs_error++;
                    } else {
                        $baseline_error++;
                    }
                    
                    // Kirim notifikasi WhatsApp untuk critical error/exception berdasarkan type
                    if ($isBpjsEndpoint) {
                        FonnteWhatsapp::sendCriticalAlert($endpoint['name'], $message, $endpoint['url'], 60);
                    } else {
                        FonnteWhatsapp::sendCriticalAlert("Internet/Baseline", "Connection failed to " . $endpoint['name'] . ": " . $message, $endpoint['url'], 60);
                    }
                }

                // Determine severity based on response time
                $severity = 'excellent';
                if ($response_time >= 2000) {
                    $severity = 'critical';
                    // Kirim notifikasi untuk response time critical (>= 2000ms) dengan cooldown 2 jam
                    FonnteWhatsapp::sendSlowResponseAlert($endpoint['name'], $response_time, $endpoint['url'], 120);
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
                    'description' => $endpoint['description'],
                    'type' => $endpoint['type'] // Tambahkan type untuk frontend
                ];

                $total_response_time += $response_time;
            }

            $total = count($results);
            $avg_response_time = $total > 0 ? round($total_response_time / $total, 2) : 0;

            return response()->json([
                'summary' => [
                    'total' => $total,
                    'success' => $success, 
                    'error' => $error,
                    'avg_response_time' => $avg_response_time,
                    'uptime_percentage' => $total > 0 ? round(($success / $total) * 100, 2) : 0,
                    
                    // Statistik terpisah untuk BPJS dan Baseline
                    'bpjs' => [
                        'success' => $bpjs_success,
                        'error' => $bpjs_error,
                        'total' => $bpjs_success + $bpjs_error,
                        'uptime_percentage' => ($bpjs_success + $bpjs_error) > 0 ? round(($bpjs_success / ($bpjs_success + $bpjs_error)) * 100, 2) : 0
                    ],
                    'baseline' => [
                        'success' => $baseline_success,
                        'error' => $baseline_error,  
                        'total' => $baseline_success + $baseline_error,
                        'uptime_percentage' => ($baseline_success + $baseline_error) > 0 ? round(($baseline_success / ($baseline_success + $baseline_error)) * 100, 2) : 0
                    ]
                ],
                'endpoints' => $results,
                'alerts' => [],
                'statistics' => [
                    'hourly_data' => [],
                    'trends' => [
                        'response_time_trend' => 'stable',
                        'uptime_trend' => 'stable'
                    ]
                ],
                'timestamp' => Carbon::now()->format('Y-m-d H:i:s')
            ]);
            
            // Diagnosis dan alert jika ada masalah
            $bpjsHasError = $bpjs_error > 0;
            $baselineHasError = $baseline_error > 0;
            
            if ($bpjsHasError || $baselineHasError) {
                $bpjsStatus = $bpjsHasError ? 'error' : 'success';
                $baselineStatus = $baselineHasError ? 'error' : 'success';
                
                // Kirim diagnosis alert
                FonnteWhatsapp::sendDiagnosisAlert($bpjsStatus, $baselineStatus, 90);
            }

        } catch (\Exception $e) {
            Log::error('BPJS Monitoring Error: ' . $e->getMessage());
            
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
                'summary' => [
                    'total' => 0,
                    'success' => 0,
                    'error' => 0,
                    'avg_response_time' => 0,
                    'uptime_percentage' => 0
                ],
                'endpoints' => [],
                'alerts' => [],
                'statistics' => [
                    'hourly_data' => [],
                    'trends' => []
                ]
            ], 500);
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
            
            if ($isBpjsEndpoint) {
                // For BPJS endpoints, use proper authentication headers
                $headers = $this->getBpjsHeaders();

                if (strtoupper($method) === 'PING') {
                    // HEAD ping with fallback to GET
                    $start = microtime(true);
                    $response = Http::withHeaders($headers)
                        ->timeout($timeout)
                        ->head($url);
                    if ($response->status() === 405) {
                        $start = microtime(true);
                        $response = Http::withHeaders($headers)
                            ->timeout($timeout)
                            ->get($url);
                    }
                } else {
                    $response = Http::withHeaders($headers)
                        ->timeout($timeout)
                        ->get($url);
                }
            } else {
                // Untuk non-BPJS endpoints, gunakan header sesuai per layanan
                $headers = [
                    'User-Agent' => 'BPJS-Monitoring/1.0',
                ];

                $endpointUrl = $url;

                if (strpos($endpointUrl, 'cloudflare-dns.com/dns-query') !== false) {
                    $headers['Accept'] = 'application/dns-json';
                    if (strpos($endpointUrl, 'ct=') === false) {
                        $endpointUrl .= (strpos($endpointUrl, '?') !== false ? '&' : '?') . 'ct=application/dns-json';
                    }
                } elseif (strpos($endpointUrl, 'api.github.com') !== false) {
                    $headers['Accept'] = 'application/vnd.github+json';
                    $headers['X-GitHub-Api-Version'] = '2022-11-28';
                    $githubToken = env('GITHUB_TOKEN');
                    if (!empty($githubToken)) {
                        $headers['Authorization'] = 'Bearer ' . $githubToken;
                    }
                } else {
                    $headers['Accept'] = 'application/json';
                }

                if (strtoupper($method) === 'PING') {
                    $start = microtime(true);
                    $response = Http::withHeaders($headers)
                        ->timeout($timeout)
                        ->head($endpointUrl);
                    if ($response->status() === 405) {
                        $start = microtime(true);
                        $response = Http::withHeaders($headers)
                            ->timeout($timeout)
                            ->get($endpointUrl);
                    }
                } else {
                    $response = Http::withHeaders($headers)
                        ->timeout($timeout)
                        ->get($endpointUrl);
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
                
                // Kirim notifikasi jika code 201 atau 404 (BPJS endpoint) dengan cooldown 30 menit
                if (in_array($code, [201, '201', 404, '404'])) {
                    FonnteWhatsapp::sendEndpointAlert("Custom Test BPJS", $code, $message, $url, 30);
                }
            } else {
                $code = $response->status();
                $message = $response->reason() ?? 'OK';
                // For PING, consider 2xx-3xx as reachable
                $status = (strtoupper($method) === 'PING')
                    ? (($code >= 200 && $code < 400) ? 'success' : 'error')
                    : ($response->successful() ? 'success' : 'error');
                
                // Kirim notifikasi jika code 201 atau 404 (Non-BPJS endpoint) dengan cooldown 30 menit
                if (in_array($code, [201, '201', 404, '404'])) {
                    FonnteWhatsapp::sendEndpointAlert("Custom Test", $code, $message, $url, 30);
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
            
        } catch (\Exception $e) {
            $end = microtime(true);
            $responseTime = round(($end - $start) * 1000);
            
            // Kirim notifikasi WhatsApp untuk critical error/exception dengan cooldown 60 menit
            FonnteWhatsapp::sendCriticalAlert("Custom Test", $e->getMessage(), $url, 60);
            
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
