<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use App\Models\SensorReading;
use App\Helpers\FonnteWhatsapp;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Run monitoring every 5 minutes; prevent overlap if previous run still active
        $schedule->command('bpjs:monitor')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        $schedule->call(function () {
            $offlineMinutes = (int) env('SENSOR_OFFLINE_MINUTES', 5);
            $cooldownMinutes = (int) env('SENSOR_OFFLINE_COOLDOWN', (int) (Config::get('sensors.whatsapp.cooldown_minutes') ?? 15));
            $recipient = env('FONNTE_TARGET');

            $since = now()->subMinutes($offlineMinutes);
            $rows = SensorReading::select('device_id')
                ->selectRaw('MAX(recorded_at) as last_at')
                ->groupBy('device_id')
                ->get();

            foreach ($rows as $row) {
                $deviceId = (string) $row->device_id;
                $lastAt = $row->last_at ? \Carbon\Carbon::parse($row->last_at) : null;

                if (!$lastAt || $lastAt->lessThanOrEqualTo($since)) {
                    $message = "⚠️ DEVICE OFFLINE\n";
                    $message .= "Device: {$deviceId}\n";
                    $message .= "Terakhir: " . ($lastAt ? $lastAt->format('Y-m-d H:i:s') : 'Tidak ada data') . "\n";
                    $message .= "Ambang: {$offlineMinutes} menit";

                    $cacheKey = 'offline_alert_' . md5($deviceId);
                    if (!Cache::has($cacheKey)) {
                        FonnteWhatsapp::sendSensorAlert($message, $cooldownMinutes, $recipient);
                        Cache::put($cacheKey, true, now()->addMinutes($cooldownMinutes));
                        Log::info('Offline alert sent', [ 'device_id' => $deviceId, 'last_at' => $lastAt ? $lastAt->toDateTimeString() : null ]);
                    } else {
                        Log::info('Offline alert skipped (cooldown active)', [ 'device_id' => $deviceId ]);
                    }
                }
            }
        })->everyMinute()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}