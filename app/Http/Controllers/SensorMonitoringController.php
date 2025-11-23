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
            // Base query (filtered by device if provided)
            $baseQuery = SensorReading::query()
                ->from('sensors')
                ->whereBetween('recorded_at', [$startDate, $endDate]);
            if ($deviceId) {
                $baseQuery->where('device_id', $deviceId);
            }

            // 1) Daily aggregates in the database to avoid loading all rows
            $dailyStats = SensorReading::query()
                ->from('sensors')
                ->selectRaw('DATE(recorded_at) as date, AVG(temperature_c) as avg_temperature, AVG(humidity) as avg_humidity, COUNT(*) as readings_count')
                ->whereBetween('recorded_at', [$startDate, $endDate])
                ->when($deviceId, function ($q) use ($deviceId) {
                    $q->where('device_id', $deviceId);
                })
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->keyBy('date');

            // 2) Morning and evening readings: pick the first reading in a small time window
            //    Morning window: 08:00-10:00; Evening window: 16:00-18:00
            $morningReadings = SensorReading::query()
                ->from('sensors')
                ->selectRaw('DATE(recorded_at) as date, recorded_at, temperature_c, humidity, device_id')
                ->whereBetween('recorded_at', [$startDate->copy()->setTime(8, 0, 0), $endDate])
                ->when($deviceId, function ($q) use ($deviceId) {
                    $q->where('device_id', $deviceId);
                })
                ->whereRaw("TIME(recorded_at) >= '08:00:00' AND TIME(recorded_at) < '10:00:00'")
                ->orderBy('recorded_at')
                ->get()
                ->groupBy('date')
                ->map(function ($group) {
                    return $group->first();
                });

            $eveningReadings = SensorReading::query()
                ->from('sensors')
                ->selectRaw('DATE(recorded_at) as date, recorded_at, temperature_c, humidity, device_id')
                ->whereBetween('recorded_at', [$startDate->copy()->setTime(16, 0, 0), $endDate])
                ->when($deviceId, function ($q) use ($deviceId) {
                    $q->where('device_id', $deviceId);
                })
                ->whereRaw("TIME(recorded_at) >= '16:00:00' AND TIME(recorded_at) < '18:00:00'")
                ->orderBy('recorded_at')
                ->get()
                ->groupBy('date')
                ->map(function ($group) {
                    return $group->first();
                });

            // Build report day by day using the aggregated results
            $report = [];
            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                $dateStr = $currentDate->format('Y-m-d');

                $stats = $dailyStats->get($dateStr);
                $morning = $morningReadings->get($dateStr);
                $evening = $eveningReadings->get($dateStr);

                $dailyData = [
                    'date' => $dateStr,
                    'avg_temperature' => $stats ? round((float) $stats->avg_temperature, 2) : null,
                    'avg_humidity' => $stats ? round((float) $stats->avg_humidity, 2) : null,
                    'readings_count' => $stats ? (int) $stats->readings_count : 0,
                    'morning_reading' => null,
                    'evening_reading' => null,
                ];

                if ($morning) {
                    $dailyData['morning_reading'] = [
                        'time' => \Carbon\Carbon::parse($morning->recorded_at)->format('H:i:s'),
                        'temperature_c' => (float) $morning->temperature_c,
                        'humidity' => (float) $morning->humidity,
                        'device_id' => $morning->device_id,
                    ];
                }

                if ($evening) {
                    $dailyData['evening_reading'] = [
                        'time' => \Carbon\Carbon::parse($evening->recorded_at)->format('H:i:s'),
                        'temperature_c' => (float) $evening->temperature_c,
                        'humidity' => (float) $evening->humidity,
                        'device_id' => $evening->device_id,
                    ];
                }

                $report[] = $dailyData;
                $currentDate->addDay();
            }

            // 3) Overall stats using aggregate queries and selective single-row lookups
            $overallRow = (clone $baseQuery)
                ->selectRaw('AVG(temperature_c) as avg_temperature, AVG(humidity) as avg_humidity, MIN(temperature_c) as min_temperature, MAX(temperature_c) as max_temperature, MIN(humidity) as min_humidity, MAX(humidity) as max_humidity, COUNT(*) as total_readings')
                ->first();

            $overallStats = null;
            if ($overallRow && (int) $overallRow->total_readings > 0) {
                $maxTempRecord = (clone $baseQuery)->orderByDesc('temperature_c')->orderByDesc('recorded_at')->first();
                $minTempRecord = (clone $baseQuery)->orderBy('temperature_c')->orderBy('recorded_at')->first();
                $maxHumidityRecord = (clone $baseQuery)->orderByDesc('humidity')->orderByDesc('recorded_at')->first();
                $minHumidityRecord = (clone $baseQuery)->orderBy('humidity')->orderBy('recorded_at')->first();

                $overallStats = [
                    'max_temperature' => round((float) $overallRow->max_temperature, 2),
                    'min_temperature' => round((float) $overallRow->min_temperature, 2),
                    'max_humidity' => round((float) $overallRow->max_humidity, 2),
                    'min_humidity' => round((float) $overallRow->min_humidity, 2),
                    'avg_temperature' => round((float) $overallRow->avg_temperature, 2),
                    'avg_humidity' => round((float) $overallRow->avg_humidity, 2),
                    'total_readings' => (int) $overallRow->total_readings,

                    'max_temp_record' => $maxTempRecord ? [
                        'temperature_c' => (float) $maxTempRecord->temperature_c,
                        'humidity' => (float) $maxTempRecord->humidity,
                        'recorded_at' => $maxTempRecord->recorded_at->toIso8601String(),
                        'device_id' => $maxTempRecord->device_id,
                    ] : null,
                    'min_temp_record' => $minTempRecord ? [
                        'temperature_c' => (float) $minTempRecord->temperature_c,
                        'humidity' => (float) $minTempRecord->humidity,
                        'recorded_at' => $minTempRecord->recorded_at->toIso8601String(),
                        'device_id' => $minTempRecord->device_id,
                    ] : null,
                    'max_humidity_record' => $maxHumidityRecord ? [
                        'temperature_c' => (float) $maxHumidityRecord->temperature_c,
                        'humidity' => (float) $maxHumidityRecord->humidity,
                        'recorded_at' => $maxHumidityRecord->recorded_at->toIso8601String(),
                        'device_id' => $maxHumidityRecord->device_id,
                    ] : null,
                    'min_humidity_record' => $minHumidityRecord ? [
                        'temperature_c' => (float) $minHumidityRecord->temperature_c,
                        'humidity' => (float) $minHumidityRecord->humidity,
                        'recorded_at' => $minHumidityRecord->recorded_at->toIso8601String(),
                        'device_id' => $minHumidityRecord->device_id,
                    ] : null,
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