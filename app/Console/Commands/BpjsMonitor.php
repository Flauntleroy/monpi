<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\BpjsEndpointConfig;
use App\Models\BpjsMonitoringLog;
use Carbon\Carbon;

class BpjsMonitor extends Command
{
    protected $signature = 'bpjs:monitor {--once : Run a single pass and exit}';
    protected $description = 'Run BPJS and custom endpoint monitoring checks and log results';

    public function handle(): int
    {
        $endpoints = BpjsEndpointConfig::active()->get();

        if ($endpoints->isEmpty()) {
            $this->warn('No active endpoints configured in BpjsEndpointConfig.');
            return self::SUCCESS;
        }

        foreach ($endpoints as $endpoint) {
            $this->checkEndpoint($endpoint);
        }

        $this->info('Monitoring run completed: ' . now());
        return self::SUCCESS;
    }

    protected function checkEndpoint(BpjsEndpointConfig $endpoint): void
    {
        $start = microtime(true);
        $statusCode = null;
        $status = 'error';
        $statusMessage = '';
        $responseHeaders = [];
        $errorDetails = null;

        try {
            // Prefer HEAD ping, fallback to GET
            $response = Http::withHeaders($endpoint->custom_headers ?? [])
                ->timeout($endpoint->timeout_seconds ?? 10)
                ->head($endpoint->url);

            $statusCode = $response->status();
            $responseHeaders = $response->headers();
            $statusMessage = 'OK';
        } catch (\Throwable $e) {
            try {
                $response = Http::withHeaders($endpoint->custom_headers ?? [])
                    ->timeout($endpoint->timeout_seconds ?? 10)
                    ->get($endpoint->url);
                $statusCode = $response->status();
                $responseHeaders = $response->headers();
                $statusMessage = 'GET fallback';
            } catch (\Throwable $e2) {
                $errorDetails = $e2->getMessage();
                $statusMessage = 'Request failed';
            }
        }

        $elapsedMs = (int) round((microtime(true) - $start) * 1000);
        if (is_int($statusCode)) {
            $status = ($statusCode >= 200 && $statusCode < 400) ? 'success' : 'error';
        }

        try {
            BpjsMonitoringLog::create([
                'endpoint_name' => $endpoint->name,
                'endpoint_url' => $endpoint->url,
                'response_time' => $elapsedMs,
                'status_code' => $statusCode ?? 0,
                'status_message' => $statusMessage,
                'status' => $status,
                'response_headers' => $responseHeaders,
                'error_details' => $errorDetails,
                'checked_at' => Carbon::now(),
            ]);
        } catch (\Throwable $logErr) {
            Log::error('Failed to write monitoring log', [
                'endpoint' => $endpoint->name,
                'error' => $logErr->getMessage(),
            ]);
        }

        $this->line(sprintf(
            '%s | %s | code=%s | %dms',
            $endpoint->name,
            $status,
            $statusCode ?? 'ERR',
            $elapsedMs
        ));
    }
}