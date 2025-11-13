<?php

// Test file untuk memastikan controller dapat membaca data dari tabel sensors
// File ini untuk testing tanpa Laravel framework

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Simulasi data dari tabel sensors
$mockData = [
    'summary' => [
        'total_readings' => 100,
        'devices_count' => 1,
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
            'device_id' => 'Servo DHT22',
            'temperature_c' => 25.8,
            'humidity' => 64.5,
            'recorded_at' => date('c', time() - 120),
            'status' => 'online',
            'last_seen_minutes' => 2,
        ],
        [
            'device_id' => 'Servo DHT22',
            'temperature_c' => 24.9,
            'humidity' => 67.2,
            'recorded_at' => date('c', time() - 480),
            'status' => 'warning',
            'last_seen_minutes' => 8,
        ],
        [
            'device_id' => 'Servo DHT22',
            'temperature_c' => 26.1,
            'humidity' => 62.8,
            'recorded_at' => date('c', time() - 1200),
            'status' => 'offline',
            'last_seen_minutes' => 20,
        ],
    ],
    'recent_readings' => [],
    'devices_list' => ['Servo DHT22'],
    'timestamp' => date('c'),
];

// Generate dummy readings untuk testing (100 data terakhir, interval 5 detik)
for ($i = 0; $i < 100; $i++) {
    $timestamp = time() - ($i * 5);
    $mockData['recent_readings'][] = [
        'id' => $i + 1,
        'device_id' => 'Servo DHT22',
        'temperature_c' => 24 + (rand(0, 200) / 10),
        'humidity' => 55 + (rand(0, 300) / 10),
        'recorded_at' => date('c', $timestamp),
    ];
}

echo json_encode($mockData, JSON_PRETTY_PRINT);