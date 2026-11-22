<?php

namespace App\Http\Controllers;

use App\Services\IncidentPhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    /**
     * Production health probe for load balancers and monitoring agents.
     * Returns 200 OK when healthy, 503 Service Unavailable when degraded.
     * Crucially suppresses internal hostnames, credentials, and SQL error messages.
     */
    public function __invoke(): JsonResponse
    {
        $checks = [];
        $isHealthy = true;

        // 1. Database Check
        try {
            DB::connection()->getPdo();
            $checks['database'] = 'ok';
        } catch (\Throwable $e) {
            $checks['database'] = 'unhealthy';
            $isHealthy = false;
            Log::error('Health probe: Database connectivity failure', [
                'exception' => class_basename($e),
            ]);
        }

        // 2. Cache Store Check
        try {
            $testKey = 'health_probe_'.time();
            Cache::put($testKey, 'ok', 5);
            $cacheVal = Cache::get($testKey);
            Cache::forget($testKey);

            $checks['cache'] = ($cacheVal === 'ok') ? 'ok' : 'unhealthy';
            if ($checks['cache'] !== 'ok') {
                $isHealthy = false;
            }
        } catch (\Throwable $e) {
            $checks['cache'] = 'unhealthy';
            $isHealthy = false;
            Log::error('Health probe: Cache store failure', [
                'exception' => class_basename($e),
            ]);
        }

        // 3. Storage Directory & Private Incident Photo Disk Writeability Check
        try {
            $isDirWritable = function (string $dir): bool {
                if (is_writable($dir)) {
                    return true;
                }
                $probe = $dir.'/.write_probe_'.bin2hex(random_bytes(4));
                $ok = @file_put_contents($probe, 'ok') !== false;
                if ($ok) {
                    @unlink($probe);
                }

                return $ok;
            };

            $frameworkWritable = $isDirWritable(storage_path('framework')) && $isDirWritable(storage_path('logs'));

            // Probe configured private incident-photo disk with a safe temporary probe file
            $photoDisk = Storage::disk(IncidentPhotoService::DISK);
            $probeFile = '.health_probe_'.time().'_'.bin2hex(random_bytes(4));
            $probeWritten = $photoDisk->put($probeFile, 'ok');
            $probeRead = $probeWritten && ($photoDisk->get($probeFile) === 'ok');
            if ($probeWritten) {
                $photoDisk->delete($probeFile);
            }

            $storageOk = $frameworkWritable && $probeRead;
            $checks['storage'] = $storageOk ? 'ok' : 'unhealthy';
            if (! $storageOk) {
                $isHealthy = false;
                Log::error('Health probe: Storage check failure', [
                    'framework_writable' => $frameworkWritable,
                    'private_disk_probe' => $probeRead,
                ]);
            }
        } catch (\Throwable $e) {
            $checks['storage'] = 'unhealthy';
            $isHealthy = false;
            Log::error('Health probe: Storage exception', [
                'exception' => class_basename($e),
            ]);
        }

        // 4. Scheduler Heartbeat
        $schedulerHeartbeat = Cache::get('nightlife:scheduler-heartbeat');
        $checks['scheduler'] = $schedulerHeartbeat ? 'ok' : 'not_reported';

        // 5. Queue Configuration and Pending Work
        $queueConnection = config('queue.default');
        if ($queueConnection === 'sync') {
            $checks['queue'] = 'not_running';
        } else {
            try {
                $pending = DB::table(config('queue.connections.database.table', 'jobs'))->count();
                $checks['queue'] = 'ok';
                $checks['queue_pending'] = $pending;
            } catch (\Throwable $e) {
                $checks['queue'] = 'unknown';
            }
        }

        // 6. Gemini Live Status
        if (! config('nightlife.ai_enabled')) {
            $checks['gemini'] = 'disabled';
        } elseif (! filled(config('services.gemini.key'))) {
            $checks['gemini'] = 'not_configured';
        } else {
            try {
                $gemini = Http::timeout(8)
                    ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
                    ->post('https://generativelanguage.googleapis.com/v1beta/models/'.config('services.gemini.model').':generateContent', [
                        'contents' => [['parts' => [['text' => 'Reply OK']]]],
                        'generationConfig' => ['maxOutputTokens' => 4, 'temperature' => 0],
                    ]);
                $checks['gemini'] = $gemini->successful() ? 'ok' : 'provider_failure';
            } catch (\Throwable $e) {
                $checks['gemini'] = 'timeout';
            }
        }

        // 7. Routing Provider Status
        $routingDriver = config('nightlife.routing_driver');
        if ($routingDriver === 'ors' && filled(config('services.ors.key'))) {
            try {
                $route = Http::timeout(8)
                    ->withHeaders(['Authorization' => config('services.ors.key'), 'Content-Type' => 'application/json'])
                    ->post('https://api.openrouteservice.org/v2/directions/driving-car/geojson', [
                        'coordinates' => [[90.4125, 23.7806], [90.4140, 23.7820]],
                    ]);
                $checks['routing'] = $route->successful() ? 'ok' : 'provider_failure';
            } catch (\Throwable $e) {
                $checks['routing'] = 'timeout';
            }
        } elseif ($routingDriver === 'osrm') {
            $checks['routing'] = 'public_osrm_not_production_ready';
        } else {
            $checks['routing'] = 'not_configured';
        }

        // 8. Mail and Telegram State
        $checks['mail'] = in_array(config('mail.default'), ['log', 'array'], true)
            ? 'local_only'
            : 'configured';

        if (! filled(config('services.telegram.token'))) {
            $checks['telegram'] = 'not_configured';
        } else {
            try {
                $telegram = Http::timeout(8)->get('https://api.telegram.org/bot'.config('services.telegram.token').'/getMe');
                $checks['telegram'] = ($telegram->successful() && $telegram->json('ok') === true)
                    ? 'ok'
                    : 'provider_failure';
            } catch (\Throwable $e) {
                $checks['telegram'] = 'timeout';
            }
        }
        $checks['sms'] = 'not_configured';

        $statusCode = $isHealthy ? 200 : 503;

        return response()->json([
            'status' => $isHealthy ? 'ok' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $statusCode);
    }
}
