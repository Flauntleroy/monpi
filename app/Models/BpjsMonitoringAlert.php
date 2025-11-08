<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BpjsMonitoringAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'endpoint_name',
        'alert_type',
        'alert_message',
        'alert_data',
        'is_resolved',
        'triggered_at',
        'resolved_at'
    ];

    protected $casts = [
        'alert_data' => 'array',
        'is_resolved' => 'boolean',
        'triggered_at' => 'datetime',
        'resolved_at' => 'datetime'
    ];

    public function scopeActive($query)
    {
        return $query->where('is_resolved', false);
    }

    public function scopeForEndpoint($query, $endpointName)
    {
        return $query->where('endpoint_name', $endpointName);
    }

    public function resolve()
    {
        $this->update([
            'is_resolved' => true,
            'resolved_at' => now()
        ]);
    }
}
