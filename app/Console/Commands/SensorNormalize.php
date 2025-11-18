<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SensorNormalize extends Command
{
    protected $signature = 'sensor:normalize {--force-id=Servo DHT22} {--delete=nodemcu_01}';
    protected $description = 'Normalize sensors device_id and delete unwanted device records';

    public function handle()
    {
        $forceId = (string) $this->option('force-id');
        $deleteId = (string) $this->option('delete');

        $conn = DB::connection('mysql');

        $deleted = $conn->table('sensors')->where('device_id', $deleteId)->delete();
        $this->line('Deleted rows for ' . $deleteId . ': ' . $deleted);

        $updated = $conn->table('sensors')->where('device_id', '!=', $forceId)->update(['device_id' => $forceId]);
        $this->line('Updated rows to ' . $forceId . ': ' . $updated);

        return 0;
    }
}