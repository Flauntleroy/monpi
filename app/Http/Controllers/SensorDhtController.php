<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use App\Models\SensorReading;
use App\Helpers\FonnteWhatsapp;

class SensorDhtController extends Controller
{
    /**
     * Accept readings from NodeMCU (ESP8266) with DHT22.
     * Expected JSON: { device_id, temperature_c, humidity, recorded_at? }
     * Uses header `X-API-KEY` or query `key` to authenticate.
     */
    public function store(Request $request)
    {
        $apiKey = Config::get('sensors.api_key');
        $providedKey = $request->header('X-API-KEY') ?: $request->query('key');

        if ($apiKey && $providedKey !== $apiKey) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $request->json()->all() ?: $request->all();
        if (empty($data)) {
            $raw = $request->getContent();
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
        if (!isset($data['temperature_c']) && isset($data['temperature'])) {
            $data['temperature_c'] = $data['temperature'];
        }

        $validator = Validator::make($data, [
            'device_id' => 'required|string|max:64',
            'temperature_c' => 'required|numeric',
            'humidity' => 'required|numeric',
            'recorded_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();
        $payload['recorded_at'] = $payload['recorded_at'] ?? now();

        $reading = SensorReading::create($payload);

        $thresholds = Config::get('sensors.thresholds');
        $waConfig = Config::get('sensors.whatsapp');
        $alerts = [];

        if (isset($thresholds['temperature_high_c']) && $reading->temperature_c > (float) $thresholds['temperature_high_c']) {
            $alerts[] = sprintf('Suhu tinggi: %.2f°C (> %.2f°C)', $reading->temperature_c, (float) $thresholds['temperature_high_c']);
        }
        if (isset($thresholds['temperature_low_c']) && $reading->temperature_c < (float) $thresholds['temperature_low_c']) {
            $alerts[] = sprintf('Suhu rendah: %.2f°C (< %.2f°C)', $reading->temperature_c, (float) $thresholds['temperature_low_c']);
        }
        if (isset($thresholds['humidity_high']) && $reading->humidity > (float) $thresholds['humidity_high']) {
            $alerts[] = sprintf('Kelembaban tinggi: %.2f%% (> %.2f%%)', $reading->humidity, (float) $thresholds['humidity_high']);
        }
        if (isset($thresholds['humidity_low']) && $reading->humidity < (float) $thresholds['humidity_low']) {
            $alerts[] = sprintf('Kelembaban rendah: %.2f%% (< %.2f%%)', $reading->humidity, (float) $thresholds['humidity_low']);
        }

        if (!empty($alerts) && ($waConfig['enabled'] ?? true)) {
            $message = "Server panas bung\n";
            $message .= "Device: {$reading->device_id}\n";
            $message .= "Suhu: " . number_format($reading->temperature_c, 2) . "°C\n";
            $message .= "Kelembaban: " . number_format($reading->humidity, 2) . "%\n";
            $message .= $reading->recorded_at->format('Y-m-d H:i:s') . "\n\n";
            $message .= implode("\n", $alerts);

            FonnteWhatsapp::sendSensorAlert(
                $message,
                (int) ($waConfig['cooldown_minutes'] ?? 15),
                $waConfig['recipient'] ?? null
            );
        }

        return response()->json([
            'message' => 'Reading stored',
            'data' => [
                'id' => $reading->id,
                'device_id' => $reading->device_id,
                'temperature_c' => $reading->temperature_c,
                'humidity' => $reading->humidity,
                'recorded_at' => $reading->recorded_at,
            ],
        ], 201);
    }

    public function recent(Request $request)
    {
        $deviceId = $request->query('device_id');
        $limit = min(max((int) ($request->query('limit') ?? 100), 1), 500);

        $query = SensorReading::query()->orderByDesc('recorded_at');
        if ($deviceId) {
            $query->where('device_id', $deviceId);
        }

        $rows = $query->limit($limit)->get(['device_id', 'temperature_c', 'humidity', 'recorded_at']);
        return response()->json(['data' => $rows]);
    }

    public function alert(Request $request)
    {
        $apiKey = Config::get('sensors.api_key');
        $providedKey = $request->header('X-API-KEY') ?: $request->query('key');
        if ($apiKey && $providedKey !== $apiKey) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $request->json()->all() ?: $request->all();
        if (empty($data)) {
            $raw = $request->getContent();
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
        if (!isset($data['temperature_c']) && isset($data['temperature'])) {
            $data['temperature_c'] = $data['temperature'];
        }

        $validator = Validator::make($data, [
            'device_id' => 'required|string|max:64',
            'temperature_c' => 'required|numeric',
            'humidity' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();
        $message = "Server panas bung\n";
        $message .= "Device: {$payload['device_id']}\n";
        $message .= "Suhu: " . number_format((float) $payload['temperature_c'], 2) . "°C\n";
        $message .= "Kelembaban: " . number_format((float) $payload['humidity'], 2) . "%\n";
        $message .= now()->format('Y-m-d H:i:s');

        $cooldown = 5;
        $recipient = env('FONNTE_TARGET');
        $result = FonnteWhatsapp::sendSensorAlert($message, $cooldown, $recipient);

        return response()->json(['status' => 'alert_sent', 'result' => $result]);
    }
}