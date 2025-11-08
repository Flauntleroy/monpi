<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BpjsEndpointConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'is_active',
        'timeout_seconds',
        'warning_threshold_ms',
        'critical_threshold_ms',
        'consecutive_error_threshold',
        'custom_headers',
        'description'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'custom_headers' => 'array',
        'warning_threshold_ms' => 'decimal:2',
        'critical_threshold_ms' => 'decimal:2'
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function logs()
    {
        return $this->hasMany(BpjsMonitoringLog::class, 'endpoint_name', 'name');
    }

    public function alerts()
    {
        return $this->hasMany(BpjsMonitoringAlert::class, 'endpoint_name', 'name');
    }

    // Method untuk menentukan severity berdasarkan response time
    public function getResponseTimeSeverity($responseTime)
    {
        if ($responseTime >= $this->critical_threshold_ms) {
            return 'critical';
        } elseif ($responseTime >= $this->warning_threshold_ms) {
            return 'warning';
        }
        return 'ok';
    }
}
