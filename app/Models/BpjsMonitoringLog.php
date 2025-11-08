<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class BpjsMonitoringLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'endpoint_name',
        'endpoint_url',
        'response_time',
        'status_code',
        'status_message',
        'status',
        'response_headers',
        'error_details',
        'checked_at'
    ];

    protected $casts = [
        'response_headers' => 'array',
        'checked_at' => 'datetime',
        'response_time' => 'decimal:2'
    ];

    // Scope untuk filter berdasarkan endpoint
    public function scopeForEndpoint($query, $endpointName)
    {
        return $query->where('endpoint_name', $endpointName);
    }

    // Scope untuk filter berdasarkan status
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    // Scope untuk filter berdasarkan tanggal
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('checked_at', [$startDate, $endDate]);
    }

    // Method untuk mendapatkan rata-rata response time
    public static function getAverageResponseTime($endpointName = null, $hours = 24)
    {
        $query = static::where('checked_at', '>=', Carbon::now()->subHours($hours));
        
        if ($endpointName) {
            $query->where('endpoint_name', $endpointName);
        }
        
        return $query->avg('response_time') ?? 0;
    }

    // Method untuk mendapatkan uptime percentage
    public static function getUptimePercentage($endpointName = null, $hours = 24)
    {
        $query = static::where('checked_at', '>=', Carbon::now()->subHours($hours));
        
        if ($endpointName) {
            $query->where('endpoint_name', $endpointName);
        }
        
        $total = $query->count();
        if ($total === 0) return 100;
        
        $successful = $query->where('status', 'success')->count();
        
        return round(($successful / $total) * 100, 2);
    }

    // Method untuk mendapatkan data historical untuk chart
    public static function getHistoricalData($endpointName = null, $hours = 24, $interval = '1 hour')
    {
        $query = static::selectRaw('
            DATE_FORMAT(checked_at, "%Y-%m-%d %H:00:00") as time_bucket,
            AVG(response_time) as avg_response_time,
            COUNT(*) as total_checks,
            SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) as successful_checks
        ')
        ->where('checked_at', '>=', Carbon::now()->subHours($hours))
        ->groupBy('time_bucket')
        ->orderBy('time_bucket');

        if ($endpointName) {
            $query->where('endpoint_name', $endpointName);
        }

        return $query->get();
    }

    // Method untuk cek consecutive errors
    public static function getConsecutiveErrors($endpointName, $limit = 5)
    {
        return static::where('endpoint_name', $endpointName)
            ->orderBy('checked_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
