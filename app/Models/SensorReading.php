<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SensorReading extends Model
{
    use HasFactory;

    // Point to existing table created on remote MySQL
    protected $table = 'sensors';
    protected $connection = 'mysql';

    protected $fillable = [
        'device_id',
        'temperature_c',
        'humidity',
        'recorded_at',
    ];

    protected $casts = [
        'temperature_c' => 'float',
        'humidity' => 'float',
        'recorded_at' => 'datetime',
    ];
}