<?php

namespace App\Http\Controllers;

use App\Services\IncidentPhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

        $statusCode = $isHealthy ? 200 : 503;

        return response()->json([
            'status' => $isHealthy ? 'ok' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $statusCode);
    }
}
