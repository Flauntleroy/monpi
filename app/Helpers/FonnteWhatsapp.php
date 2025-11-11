<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class FonnteWhatsapp
{
    public static function send($message, $phone = null, $cooldownMinutes = 15)
    {
        // try {
             
        //     if (!env('FONNTE_ENABLED', true)) {
        //         Log::info('WhatsApp sending disabled via FONNTE_ENABLED');
        //         return ['status' => false, 'message' => 'WhatsApp disabled'];
        //     }

             
        //     $phone = $phone ?: env('FONNTE_TARGET', '6281256180502');
             
        //     $cacheKey = 'whatsapp_sent_' . md5($phone . '_' . $message);
            
             
        //     if (Cache::has($cacheKey)) {
        //         Log::info('WhatsApp message skipped (cooldown active)', [
        //             'phone' => $phone,
        //             'message' => $message,
        //             'cooldown_minutes' => $cooldownMinutes
        //         ]);
        //         return ['status' => 'skipped', 'message' => 'Message skipped due to cooldown'];
        //     }
            
             
        //     $token = env('FONNTE_TOKEN', 'nFi7goGNVJiG25gCbL7k');
            
        //     Log::info('Attempting to send WhatsApp message', [
        //         'phone' => $phone,
        //         'message' => $message
        //     ]);
            
        //     $response = Http::withHeaders([
        //         'Authorization' => $token
        //     ])->asForm()->post('https://api.fonnte.com/send', [
        //         'target' => $phone,
        //         'message' => $message,
        //     ]);
            
        //     $result = $response->json();
            
        //     Log::info('WhatsApp API Response', [
        //         'status' => $response->status(),
        //         'response' => $result
        //     ]);
            
             
        //     if ($response->successful() && isset($result['status']) && $result['status']) {
        //         Cache::put($cacheKey, true, now()->addMinutes($cooldownMinutes));
        //         Log::info('WhatsApp cooldown set', [
        //             'cache_key' => $cacheKey,
        //             'cooldown_minutes' => $cooldownMinutes
        //         ]);
        //     }
            
        //     return $result;
            
        // } catch (\Exception $e) {
        //     Log::error('WhatsApp sending failed', [
        //         'error' => $e->getMessage(),
        //         'phone' => $phone,
        //         'message' => $message
        //     ]);
            
        //     return ['status' => 'error', 'message' => $e->getMessage()];
        // }
    }
    
    /**
     * Send pesan dengan cooldown khusus untuk endpoint tertentu
     */
    public static function sendEndpointAlert($endpointName, $code, $message, $url, $cooldownMinutes = 30)
    {
         
        // $cacheKey = 'endpoint_alert_' . md5($endpointName . '_' . $code . '_' . $url);
        
         
        // if (Cache::has($cacheKey)) {
        //     Log::info('Endpoint alert skipped (cooldown active)', [
        //         'endpoint' => $endpointName,
        //         'code' => $code,
        //         'url' => $url,
        //         'cooldown_minutes' => $cooldownMinutes
        //     ]);
        //     return ['status' => 'skipped', 'message' => 'Alert skipped due to cooldown'];
        // }
        
        // $alertMessage = "🚨 BPJS Monitoring Alert\n";
        // $alertMessage .= "Endpoint: $endpointName\n";
        // $alertMessage .= "Status: $code\n";
        // $alertMessage .= "Message: $message\n";
        // $alertMessage .= "URL: $url\n";
        // $alertMessage .= "Time: " . date('Y-m-d H:i:s');
        
        // $result = self::sendDirect($alertMessage, env('FONNTE_TARGET', '6281256180502'));
        
         
        // if (isset($result['status']) && $result['status']) {
        //     Cache::put($cacheKey, true, now()->addMinutes($cooldownMinutes));
        //     Log::info('Endpoint alert cooldown set', [
        //         'cache_key' => $cacheKey,
        //         'cooldown_minutes' => $cooldownMinutes
        //     ]);
        // }
        
        // return $result;
    }
    
    /**
     * Send pesan critical error dengan cooldown lebih pendek
     */
    public static function sendCriticalAlert($endpointName, $error, $url, $cooldownMinutes = 60)
    {
         
        // $cacheKey = 'critical_alert_' . md5($endpointName . '_' . $url);
        
         
        // if (Cache::has($cacheKey)) {
        //     Log::info('Critical alert skipped (cooldown active)', [
        //         'endpoint' => $endpointName,
        //         'url' => $url,
        //         'cooldown_minutes' => $cooldownMinutes
        //     ]);
        //     return ['status' => 'skipped', 'message' => 'Critical alert skipped due to cooldown'];
        // }
        
        // $alertMessage = "🔥 CRITICAL ERROR\n";
        // $alertMessage .= "Endpoint: $endpointName\n";
        // $alertMessage .= "Error: $error\n";
        // $alertMessage .= "URL: $url\n";
        // $alertMessage .= "Time: " . date('Y-m-d H:i:s');
        
        // $result = self::sendDirect($alertMessage, env('FONNTE_TARGET', '6281256180502'));
        
         
        // if (isset($result['status']) && $result['status']) {
        //     Cache::put($cacheKey, true, now()->addMinutes($cooldownMinutes));
        //     Log::info('Critical alert cooldown set', [
        //         'cache_key' => $cacheKey,
        //         'cooldown_minutes' => $cooldownMinutes
        //     ]);
        // }
        
        // return $result;
    }
    
    /**
     * Send pesan slow response dengan cooldown lebih panjang
     */
    public static function sendSlowResponseAlert($endpointName, $responseTime, $url, $cooldownMinutes = 120)
    {
         
        // $cacheKey = 'slow_response_' . md5($endpointName . '_' . $url);
        
         
        // if (Cache::has($cacheKey)) {
        //     Log::info('Slow response alert skipped (cooldown active)', [
        //         'endpoint' => $endpointName,
        //         'url' => $url,
        //         'cooldown_minutes' => $cooldownMinutes
        //     ]);
        //     return ['status' => 'skipped', 'message' => 'Slow response alert skipped due to cooldown'];
        // }
        
        // $alertMessage = "⏰ Slow Response Alert\n";
        // $alertMessage .= "Endpoint: $endpointName\n";
        // $alertMessage .= "Response Time: {$responseTime}ms\n";
        // $alertMessage .= "URL: $url\n";
        // $alertMessage .= "Time: " . date('Y-m-d H:i:s');
        
        // $result = self::sendDirect($alertMessage, env('FONNTE_TARGET', '6281256180502'));
        
         
        // if (isset($result['status']) && $result['status']) {
        //     Cache::put($cacheKey, true, now()->addMinutes($cooldownMinutes));
        //     Log::info('Slow response alert cooldown set', [
        //         'cache_key' => $cacheKey,
        //         'cooldown_minutes' => $cooldownMinutes
        //     ]);
        // }
        
        // return $result;
    }
    
    /**
     * Send diagnosis alert yang menjelaskan penyebab masalah
     */
    public static function sendDiagnosisAlert($bpjsStatus, $baselineStatus, $cooldownMinutes = 90)
    {
        // $cacheKey = 'diagnosis_alert_' . md5($bpjsStatus . '_' . $baselineStatus);
        
         
        // if (Cache::has($cacheKey)) {
        //     Log::info('Diagnosis alert skipped (cooldown active)', [
        //         'bpjs_status' => $bpjsStatus,
        //         'baseline_status' => $baselineStatus,
        //         'cooldown_minutes' => $cooldownMinutes
        //     ]);
        //     return ['status' => 'skipped', 'message' => 'Diagnosis alert skipped due to cooldown'];
        // }
        
        // $alertMessage = "🔍 NETWORK DIAGNOSIS\n";
        // $alertMessage .= "Time: " . date('Y-m-d H:i:s') . "\n\n";
        
        // if ($bpjsStatus === 'error' && $baselineStatus === 'error') {
        //     $alertMessage .= "❌ INTERNET CONNECTION ISSUE\n";
        //     $alertMessage .= "• BPJS API: Failed ❌\n";
        //     $alertMessage .= "• Baseline APIs: Failed ❌\n";
        //     $alertMessage .= "• Diagnosis: Internet connection problem\n";
        //     $alertMessage .= "• Action: Check your internet connection";
        // } elseif ($bpjsStatus === 'error' && $baselineStatus === 'success') {
        //     $alertMessage .= "⚠️ BPJS API SPECIFIC ISSUE\n";
        //     $alertMessage .= "• BPJS API: Failed ❌\n";
        //     $alertMessage .= "• Baseline APIs: Working ✅\n";
        //     $alertMessage .= "• Diagnosis: BPJS server issue\n";
        //     $alertMessage .= "• Action: Problem is on BPJS side, not your connection";
        // } elseif ($bpjsStatus === 'success' && $baselineStatus === 'error') {
        //     $alertMessage .= "🔄 PARTIAL CONNECTION ISSUE\n";
        //     $alertMessage .= "• BPJS API: Working ✅\n";
        //     $alertMessage .= "• Baseline APIs: Failed ❌\n";
        //     $alertMessage .= "• Diagnosis: Partial internet/DNS issue\n";
        //     $alertMessage .= "• Action: Check DNS settings or specific network routes";
        // }
        
        // $result = self::sendDirect($alertMessage, env('FONNTE_TARGET', '6281256180502'));
        
         
        // if (isset($result['status']) && $result['status']) {
        //     Cache::put($cacheKey, true, now()->addMinutes($cooldownMinutes));
        //     Log::info('Diagnosis alert cooldown set', [
        //         'cache_key' => $cacheKey,
        //         'cooldown_minutes' => $cooldownMinutes
        //     ]);
        // }
        
        // return $result;
    }
    private static function sendDirect($message, $phone = null)
    {
        // try {
        //     if (!env('FONNTE_ENABLED', true)) {
        //         Log::info('WhatsApp direct send skipped: disabled');
        //         return ['status' => false, 'message' => 'WhatsApp disabled'];
        //     }
        //     $phone = $phone ?: env('FONNTE_TARGET', '6281256180502');
        //     $token = env('FONNTE_TOKEN', 'nFi7goGNVJiG25gCbL7k');
            
        //     Log::info('Attempting to send WhatsApp message', [
        //         'phone' => $phone,
        //         'message' => $message
        //     ]);
            
        //     $response = Http::withHeaders([
        //         'Authorization' => $token
        //     ])->asForm()->post('https://api.fonnte.com/send', [
        //         'target' => $phone,
        //         'message' => $message,
        //     ]);
            
        //     $result = $response->json();
            
        //     Log::info('WhatsApp API Response', [
        //         'status' => $response->status(),
        //         'response' => $result
        //     ]);
            
        //     return $result;
            
        // } catch (\Exception $e) {
        //     Log::error('WhatsApp sending failed', [
        //         'error' => $e->getMessage(),
        //         'phone' => $phone,
        //         'message' => $message
        //     ]);
            
        //     return ['status' => 'error', 'message' => $e->getMessage()];
        // }
    }
}
