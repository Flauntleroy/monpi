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
            
            // Get recent readings
            $query = SensorReading::query()->from('sensors')->orderByDesc('recorded_at');
            if ($deviceId) {
                $query->where('device_id', $deviceId);
            }
            
            $recentReadings = $query->limit($limit)->get();
            
            // Get summary statistics
            $summaryQuery = SensorReading::query()->from('sensors');
            if ($deviceId) {
                $summaryQuery->where('device_id', $deviceId);
            }
            
            // Get readings from last 24 hours for summary
            $last24h = $summaryQuery->where('recorded_at', '>=', now()->subHours(24))->get();
            
            $totalReadings = $recentReadings->count();
            $avgTemp = $recentReadings->avg('temperature_c');
            $avgHumidity = $recentReadings->avg('humidity');
            $minTemp = $recentReadings->min('temperature_c');
            $maxTemp = $recentReadings->max('temperature_c');
            $minHumidity = $recentReadings->min('humidity');
            $maxHumidity = $recentReadings->max('humidity');
            
            // 24h statistics
            $avgTemp24h = $last24h->avg('temperature_c');
            $avgHumidity24h = $last24h->avg('humidity');
            
            // Get unique devices
            $devices = SensorReading::query()->from('sensors')
                ->select('device_id')
                ->distinct()
                ->orderBy('device_id')
                ->pluck('device_id');
            
            // Get latest reading for each device (for device status)
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
                        'last_seen_minutes' => now()->diffInMinutes($latest->recorded_at),
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
            ]);
            
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
        $minutesAgo = now()->diffInMinutes($lastSeen);
        
        if ($minutesAgo <= 5) {
            return 'online';
        } elseif ($minutesAgo <= 15) {
            return 'warning';
        } else {
            return 'offline';
        }
    }
}