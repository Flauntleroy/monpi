<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SensorReading;
use Illuminate\Support\Facades\Log;

class SensorMonitoringController extends Controller
{
    /**
     * Get sensor monitoring dashboard data
     */
    public function index(Request $request)
    {
        try {
            $deviceId = $request->query('device_id');
            $limit = min(max((int) ($request->query('limit') ?? 100), 1), 500);
            
            
            $query = SensorReading::query()->from('sensors')->orderByDesc('recorded_at');
            if ($deviceId) {
                $query->where('device_id', $deviceId);
            }
            
            $recentReadings = $query->limit($limit)->get();
            
            
            $summaryQuery = SensorReading::query()->from('sensors');
            if ($deviceId) {
                $summaryQuery->where('device_id', $deviceId);
            }
            
            
            $last24h = $summaryQuery->where('recorded_at', '>=', now()->subHours(24))->get();
            
            $totalReadings = $recentReadings->count();
            $avgTemp = $recentReadings->avg('temperature_c');
            $avgHumidity = $recentReadings->avg('humidity');
            $minTemp = $recentReadings->min('temperature_c');
            $maxTemp = $recentReadings->max('temperature_c');
            $minHumidity = $recentReadings->min('humidity');
            $maxHumidity = $recentReadings->max('humidity');
            
            
            $avgTemp24h = $last24h->avg('temperature_c');
            $avgHumidity24h = $last24h->avg('humidity');
            
            
            $devices = SensorReading::query()->from('sensors')
                ->select('device_id')
                ->distinct()
                ->orderBy('device_id')
                ->pluck('device_id');
            
            
            $deviceStatuses = [];
            foreach ($devices as $device) {
                $latest = SensorReading::query()->from('sensors')
                    ->where('device_id', $device)
                    ->latest('recorded_at')
                    ->first();
                    
                if ($latest) {
                    $deviceStatuses[] = [
                        'device_id' => $device,
                        'temperature_c' => $latest->temperature_c,
                        'humidity' => $latest->humidity,
                        'recorded_at' => $latest->recorded_at->toIso8601String(),
                        'status' => $this->getDeviceStatus($latest->recorded_at),
                        'last_seen_minutes' => (int) round($latest->recorded_at->diffInMinutes(now())),
                    ];
                }
            }
            
            return response()->json([
                'summary' => [
                    'total_readings' => $totalReadings,
                    'devices_count' => $devices->count(),
                    'avg_temperature' => round($avgTemp, 2),
                    'avg_humidity' => round($avgHumidity, 2),
                    'min_temperature' => round($minTemp, 2),
                    'max_temperature' => round($maxTemp, 2),
                    'min_humidity' => round($minHumidity, 2),
                    'max_humidity' => round($maxHumidity, 2),
                    'avg_temperature_24h' => round($avgTemp24h, 2),
                    'avg_humidity_24h' => round($avgHumidity24h, 2),
                ],
                'devices' => $deviceStatuses,
                'recent_readings' => $recentReadings->map(function ($reading) {
                    return [
                        'id' => $reading->id,
                        'device_id' => $reading->device_id,
                        'temperature_c' => $reading->temperature_c,
                        'humidity' => $reading->humidity,
                        'recorded_at' => $reading->recorded_at->toIso8601String(),
                    ];
                }),
                'devices_list' => $devices,
                'timestamp' => now()->toIso8601String(),
            ])->header('Cache-Control', 'no-store, no-cache, must-revalidate')
              ->header('Pragma', 'no-cache')
              ->header('Expires', '0');
            
        } catch (\Exception $e) {
            Log::error('Sensor monitoring error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch sensor data',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get device status based on last seen time
     */
    private function getDeviceStatus($lastSeen)
    {
        $secondsAgo = $lastSeen->diffInSeconds(now());
        if ($secondsAgo <= 60) {
            return 'online';
        }
        if ($secondsAgo <= 300) {
            return 'warning';
        }
        return 'offline';
    }

    /**
     * Generate monthly sensor report
     */
    public function report(Request $request)
    {
        try {
            $month = $request->query('month', now()->month);
            $year = $request->query('year', now()->year);
            $deviceId = $request->query('device_id');

            $startDate = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfDay();
            $endDate = $startDate->copy()->endOfMonth()->endOfDay();

            $query = SensorReading::query()
                ->from('sensors')
                ->whereBetween('recorded_at', [$startDate, $endDate]);

            if ($deviceId) {
                $query->where('device_id', $deviceId);
            }

            $readings = $query->orderBy('recorded_at')->get();
            
            
            $grouped = $readings->groupBy(function ($item) {
                return $item->recorded_at->format('Y-m-d');
            });

            $report = [];
            $currentDate = $startDate->copy();

            while ($currentDate <= $endDate) {
                $dateStr = $currentDate->format('Y-m-d');
                $dayReadings = $grouped->get($dateStr, collect());

                $dailyData = [
                    'date' => $dateStr,
                    'avg_temperature' => $dayReadings->isNotEmpty() ? round($dayReadings->avg('temperature_c'), 2) : null,
                    'avg_humidity' => $dayReadings->isNotEmpty() ? round($dayReadings->avg('humidity'), 2) : null,
                    'readings_count' => $dayReadings->count(),
                    'morning_reading' => null,
                    'evening_reading' => null,
                ];

                if ($dayReadings->isNotEmpty()) {
                    
                    $morningTarget = $currentDate->copy()->setTime(8, 0, 0);
                    $morningReading = $dayReadings->sortBy(function ($reading) use ($morningTarget) {
                        return abs($reading->recorded_at->diffInSeconds($morningTarget));
                    })->first();

                    
                    
                    if ($morningReading) {
                        $dailyData['morning_reading'] = [
                            'time' => $morningReading->recorded_at->format('H:i:s'),
                            'temperature_c' => $morningReading->temperature_c,
                            'humidity' => $morningReading->humidity,
                        ];
                    }

                    
                    $eveningTarget = $currentDate->copy()->setTime(16, 0, 0);
                    $eveningReading = $dayReadings->sortBy(function ($reading) use ($eveningTarget) {
                        return abs($reading->recorded_at->diffInSeconds($eveningTarget));
                    })->first();

                    if ($eveningReading) {
                        $dailyData['evening_reading'] = [
                            'time' => $eveningReading->recorded_at->format('H:i:s'),
                            'temperature_c' => $eveningReading->temperature_c,
                            'humidity' => $eveningReading->humidity,
                        ];
                    }
                }

                $report[] = $dailyData;
                $currentDate->addDay();
            }

            
            $overallStats = null;
            if ($readings->isNotEmpty()) {
                
                $maxTempReading = $readings->sortByDesc('temperature_c')->first();
                $minTempReading = $readings->sortBy('temperature_c')->first();
                $maxHumidityReading = $readings->sortByDesc('humidity')->first();
                $minHumidityReading = $readings->sortBy('humidity')->first();

                $overallStats = [
                    'max_temperature' => round($readings->max('temperature_c'), 2),
                    'min_temperature' => round($readings->min('temperature_c'), 2),
                    'max_humidity' => round($readings->max('humidity'), 2),
                    'min_humidity' => round($readings->min('humidity'), 2),
                    'avg_temperature' => round($readings->avg('temperature_c'), 2),
                    'avg_humidity' => round($readings->avg('humidity'), 2),
                    'total_readings' => $readings->count(),
                    
                    'max_temp_record' => [
                        'temperature_c' => $maxTempReading->temperature_c,
                        'humidity' => $maxTempReading->humidity,
                        'recorded_at' => $maxTempReading->recorded_at->toIso8601String(),
                        'device_id' => $maxTempReading->device_id,
                    ],
                    'min_temp_record' => [
                        'temperature_c' => $minTempReading->temperature_c,
                        'humidity' => $minTempReading->humidity,
                        'recorded_at' => $minTempReading->recorded_at->toIso8601String(),
                        'device_id' => $minTempReading->device_id,
                    ],
                    'max_humidity_record' => [
                        'temperature_c' => $maxHumidityReading->temperature_c,
                        'humidity' => $maxHumidityReading->humidity,
                        'recorded_at' => $maxHumidityReading->recorded_at->toIso8601String(),
                        'device_id' => $maxHumidityReading->device_id,
                    ],
                    'min_humidity_record' => [
                        'temperature_c' => $minHumidityReading->temperature_c,
                        'humidity' => $minHumidityReading->humidity,
                        'recorded_at' => $minHumidityReading->recorded_at->toIso8601String(),
                        'device_id' => $minHumidityReading->device_id,
                    ],
                ];
            }

            return response()->json([
                'meta' => [
                    'month' => $month,
                    'year' => $year,
                    'device_id' => $deviceId,
                ],
                'overall_stats' => $overallStats,
                'data' => $report
            ]);

        } catch (\Exception $e) {
            Log::error('Sensor report error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to generate report',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}