<?php

return [
    'api_key' => env('SENSOR_API_KEY', null),

    'thresholds' => [
        'temperature_high_c' => env('SENSOR_TEMP_HIGH', 40), // Alert if > 40C
        'temperature_low_c' => env('SENSOR_TEMP_LOW', 5),   // Optional low temp
        'humidity_high' => env('SENSOR_HUMID_HIGH', 90),     // Alert if > 90%
        'humidity_low' => env('SENSOR_HUMID_LOW', 30),       // Alert if < 30%
    ],

    'whatsapp' => [
        'enabled' => env('FONNTE_ENABLED', true),
        'recipient' => env('FONNTE_TARGET', null),
        'cooldown_minutes' => env('SENSOR_ALERT_COOLDOWN', 15),
    ],
];