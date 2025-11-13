<?php

// Test file untuk memastikan controller dapat membaca data dari tabel sensors
// File ini untuk testing tanpa Laravel framework

header('Content-Type: application/json');

// Simulasi data dari tabel sensors
$mockData = [
    'summary' => [
        'total_readings' => 150,
        'devices_count' => 3,
        'avg_temperature' => 25.5,
        'avg_humidity' => 65.2,
        'min_temperature' => 18.3,
        'max_temperature' => 32.1,
        'min_humidity' => 45.8,
        'max_humidity' => 78.9,
        'avg_temperature_24h' => 26.1,
        'avg_humidity_24h' => 63.8,
    ],
    'devices' => [
        [
            'device_id' => 'nodemcu-1',
            'temperature_c' => 25.8,
            'humidity' => 64.5,
            'recorded_at' => date('c', time() - 120),
            'status' => 'online',
            'last_seen_minutes' => 2,
        ],
        [
            'device_id' => 'nodemcu-2',
            'temperature_c' => 24.9,
            'humidity' => 67.2,
            'recorded_at' => date('c', time() - 480),
            'status' => 'warning',
            'last_seen_minutes' => 8,
        ],
        [
            'device_id' => 'esp32-1',
            'temperature_c' => 26.1,
            'humidity' => 62.8,
            'recorded_at' => date('c', time() - 1200),
            'status' => 'offline',
            'last_seen_minutes' => 20,
        ],
    ],
    'recent_readings' => [],
    'devices_list' => ['nodemcu-1', 'nodemcu-2', 'esp32-1'],
    'timestamp' => date('c'),
];

// Generate dummy readings untuk testing
$devices = ['nodemcu-1', 'nodemcu-2', 'esp32-1'];
$baseTime = time() - (2 * 3600); // 2 hours ago

for ($i = 0; $i < 100; $i++) {
    $timeOffset = $i * 120; // Every 2 minutes
    $deviceIndex = $i % 3;
    
    $mockData['recent_readings'][] = [
        'id' => $i + 1,
        'device_id' => $devices[$deviceIndex],
        'temperature_c' => 20 + (rand(0, 150) / 10), // 20.0 - 35.0
        'humidity' => 40 + rand(0, 400) / 10, // 40.0 - 80.0
        'recorded_at' => date('c', $baseTime + $timeOffset),
    ];
}

echo json_encode($mockData, JSON_PRETTY_PRINT);