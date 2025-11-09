<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Helpers\FonnteWhatsapp;

class NetworkDiagnosticController extends Controller
{
    private $endpoints = [
        'bpjs' => [
            [
                'name' => 'BPJS Diagnosa',
                'url' => 'https://new-api.bpjs-kesehatan.go.id/new-vclaim-rest/diagnosa',
                'type' => 'bpjs'
            ],
            [
                'name' => 'BPJS Poli',
                'url' => 'https://new-api.bpjs-kesehatan.go.id/new-vclaim-rest/poli',
                'type' => 'bpjs'
            ],
            [
                'name' => 'BPJS Faskes',
                'url' => 'https://new-api.bpjs-kesehatan.go.id/new-vclaim-rest/referensi/faskes',
                'type' => 'bpjs'
            ]
        ],
        'baseline' => [
            [
                'name' => 'Google DNS',
                'url' => 'https://dns.google/resolve?name=google.com&type=A',
                'type' => 'dns'
            ],
            [
                'name' => 'Cloudflare DNS',
                'url' => 'https://cloudflare-dns.com/dns-query?name=cloudflare.com&type=A',
                'type' => 'dns'
            ],
            [
                'name' => 'JSONPlaceholder',
                'url' => 'https://jsonplaceholder.typicode.com/posts/1',
                'type' => 'api'
            ],
            [
                'name' => 'HTTPBin Status',
                'url' => 'https://httpbin.org/status/200',
                'type' => 'api'
            ],
            [
                'name' => 'GitHub API',
                'url' => 'https://api.github.com/zen',
                'type' => 'api'
            ]
        ]
    ];

    public function getDiagnosticData()
    {
        $diagnosticData = [
            'current_status' => $this->getCurrentStatus(),
            'response_times' => $this->getResponseTimes(),
            'status_history' => $this->getStatusHistory(),
            'uptime_stats' => $this->getUptimeStats(),
            'latency_comparison' => $this->getLatencyComparison(),
            'diagnosis' => $this->getDiagnosis(),
            'recommendations' => $this->getRecommendations(),
            'timestamp' => now()->toIso8601String()
        ];

        return response()->json($diagnosticData);
    }

    private function getCurrentStatus()
    {
        $status = ['bpjs' => [], 'baseline' => []];
        
        foreach ($this->endpoints as $type => $endpoints) {
            foreach ($endpoints as $endpoint) {
                try {
                    $startTime = microtime(true);

                    // Header per layanan baseline
                    $headers = [
                        'User-Agent' => 'BPJS-Monitoring/1.0',
                    ];

                    $endpointUrl = $endpoint['url'];

                    // Khusus Cloudflare DoH
                    if (strpos($endpointUrl, 'cloudflare-dns.com/dns-query') !== false) {
                        $headers['Accept'] = 'application/dns-json';
                        if (strpos($endpointUrl, 'ct=') === false) {
                            $endpointUrl .= (strpos($endpointUrl, '?') !== false ? '&' : '?') . 'ct=application/dns-json';
                        }
                    // Khusus GitHub API
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

                    $response = Http::timeout(10)
                        ->withHeaders($headers)
                        ->get($endpointUrl);
                    $endTime = microtime(true);
                    
                    $responseTime = round(($endTime - $startTime) * 1000); // Convert to ms
                    
                    $status[$type][] = [
                        'name' => $endpoint['name'],
                        'status' => $response->successful() ? 'success' : 'error',
                        'code' => $response->status(),
                        'response_time' => $responseTime,
                        'timestamp' => now()->toIso8601String()
                    ];

                    // Save to history
                    $this->saveToHistory($endpoint['name'], [
                        'status' => $response->successful() ? 'success' : 'error',
                        'code' => $response->status(),
                        'response_time' => $responseTime,
                        'timestamp' => now()->toIso8601String()
                    ]);

                } catch (\Exception $e) {
                    $status[$type][] = [
                        'name' => $endpoint['name'],
                        'status' => 'error',
                        'code' => 0,
                        'error' => $e->getMessage(),
                        'timestamp' => now()->toIso8601String()
                    ];

                    // Save failed attempt to history
                    $this->saveToHistory($endpoint['name'], [
                        'status' => 'error',
                        'code' => 0,
                        'error' => $e->getMessage(),
                        'timestamp' => now()->toIso8601String()
                    ]);
                }
            }
        }

        return $status;
    }

    private function saveToHistory($endpointName, $data)
    {
        $cacheKey = "endpoint_history_{$endpointName}";
        $history = Cache::get($cacheKey, []);
        
        // Keep last 100 records only
        array_unshift($history, $data);
        if (count($history) > 100) {
            array_pop($history);
        }
        
        Cache::put($cacheKey, $history, now()->addDays(7));
    }

    private function getResponseTimes()
    {
        $times = ['bpjs' => [], 'baseline' => []];
        
        foreach ($this->endpoints as $type => $endpoints) {
            foreach ($endpoints as $endpoint) {
                $history = Cache::get("endpoint_history_{$endpoint['name']}", []);
                
                if (!empty($history)) {
                    $lastResponseTime = $history[0]['response_time'] ?? null;
                    $avgResponseTime = collect($history)
                        ->pluck('response_time')
                        ->filter()
                        ->avg();
                    
                    $times[$type][] = [
                        'name' => $endpoint['name'],
                        'current' => $lastResponseTime,
                        'average' => round($avgResponseTime, 2)
                    ];
                }
            }
        }
        
        return $times;
    }

    private function getStatusHistory()
    {
        $history = ['bpjs' => [], 'baseline' => []];
        
        foreach ($this->endpoints as $type => $endpoints) {
            foreach ($endpoints as $endpoint) {
                $endpointHistory = Cache::get("endpoint_history_{$endpoint['name']}", []);
                
                $history[$type][] = [
                    'name' => $endpoint['name'],
                    'history' => collect($endpointHistory)->take(20)->toArray()
                ];
            }
        }
        
        return $history;
    }

    private function getUptimeStats()
    {
        $stats = ['bpjs' => [], 'baseline' => []];
        
        foreach ($this->endpoints as $type => $endpoints) {
            foreach ($endpoints as $endpoint) {
                $history = Cache::get("endpoint_history_{$endpoint['name']}", []);
                
                if (!empty($history)) {
                    $total = count($history);
                    $successful = collect($history)
                        ->where('status', 'success')
                        ->count();
                    
                    $uptimePercentage = $total > 0 ? round(($successful / $total) * 100, 2) : 0;
                    
                    $stats[$type][] = [
                        'name' => $endpoint['name'],
                        'uptime' => $uptimePercentage,
                        'total_checks' => $total,
                        'successful' => $successful
                    ];
                }
            }
        }
        
        return $stats;
    }

    private function getLatencyComparison()
    {
        $bpjsAvg = collect($this->endpoints['bpjs'])
            ->map(function ($endpoint) {
                $history = Cache::get("endpoint_history_{$endpoint['name']}", []);
                return collect($history)
                    ->pluck('response_time')
                    ->filter()
                    ->avg();
            })
            ->filter()
            ->avg();

        $baselineAvg = collect($this->endpoints['baseline'])
            ->map(function ($endpoint) {
                $history = Cache::get("endpoint_history_{$endpoint['name']}", []);
                return collect($history)
                    ->pluck('response_time')
                    ->filter()
                    ->avg();
            })
            ->filter()
            ->avg();

        return [
            'bpjs_avg' => round($bpjsAvg, 2),
            'baseline_avg' => round($baselineAvg, 2),
            'difference' => round($bpjsAvg - $baselineAvg, 2),
            'ratio' => $baselineAvg > 0 ? round($bpjsAvg / $baselineAvg, 2) : 0
        ];
    }

    private function getDiagnosis()
    {
        $currentStatus = $this->getCurrentStatus();
        
        $bpjsFailed = collect($currentStatus['bpjs'])
            ->where('status', 'error')
            ->count();
        
        $baselineFailed = collect($currentStatus['baseline'])
            ->where('status', 'error')
            ->count();
        
        $latencyComparison = $this->getLatencyComparison();
        
        $diagnosis = [];
        
        // Check connection status
        if ($bpjsFailed > 0 && $baselineFailed > 0) {
            $diagnosis[] = "⚠️ General connectivity issues detected";
        } elseif ($bpjsFailed > 0 && $baselineFailed == 0) {
            $diagnosis[] = "🔍 BPJS-specific issues detected";
        } elseif ($bpjsFailed == 0 && $baselineFailed > 0) {
            $diagnosis[] = "🌐 Partial network issues (BPJS still accessible)";
        }
        
        // Check latency
        if ($latencyComparison['ratio'] > 2) {
            $diagnosis[] = "⏱️ BPJS endpoints are significantly slower than baseline";
        }
        
        // If everything looks good
        if (empty($diagnosis)) {
            $diagnosis[] = "✅ All systems operating normally";
        }
        
        return $diagnosis;
    }

    private function getRecommendations()
    {
        $currentStatus = $this->getCurrentStatus();
        $latencyComparison = $this->getLatencyComparison();
        
        $recommendations = [];
        
        // Connection-based recommendations
        $bpjsFailed = collect($currentStatus['bpjs'])->where('status', 'error')->count();
        $baselineFailed = collect($currentStatus['baseline'])->where('status', 'error')->count();
        
        if ($bpjsFailed > 0 && $baselineFailed > 0) {
            $recommendations[] = "🔧 Check your internet connection";
            $recommendations[] = "📡 Verify DNS settings";
            $recommendations[] = "🔄 Try using a different network";
        } elseif ($bpjsFailed > 0 && $baselineFailed == 0) {
            $recommendations[] = "⏳ Wait for BPJS system recovery";
            $recommendations[] = "📞 Contact BPJS support if persistent";
            $recommendations[] = "🔄 Try clearing your BPJS session";
        }
        
        // Latency-based recommendations
        if ($latencyComparison['ratio'] > 2) {
            $recommendations[] = "🚀 Consider using a faster DNS server";
            $recommendations[] = "🌐 Check for network congestion";
        }
        
        // If no specific recommendations needed
        if (empty($recommendations)) {
            $recommendations[] = "✅ No action needed - all systems optimal";
        }
        
        return $recommendations;
    }
}
