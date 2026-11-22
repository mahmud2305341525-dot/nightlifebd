<?php

namespace App\Services;

use App\Models\AiTaskUsage;
use Illuminate\Support\Facades\Cache;

class AiTaskService
{
    public function __construct(private GeminiService $gemini) {}

    public function json(string $feature, string $promptVersion, string $prompt, int $dailyBudget = 20): ?array
    {
        if (! (bool) config('nightlife.ai_enabled', true)) {
            $this->log($feature, $promptVersion, 'disabled', 0);

            return null;
        }

        $circuitKey = 'nightlife:ai:circuit:'.$feature;
        if ((bool) Cache::get($circuitKey, false)) {
            $this->log($feature, $promptVersion, 'circuit_open', 0);

            return null;
        }

        $budgetKey = 'nightlife:ai:budget:'.$feature.':'.now()->format('Y-m-d');
        $used = (int) Cache::get($budgetKey, 0);
        if ($used >= $dailyBudget) {
            $this->log($feature, $promptVersion, 'budget_exceeded', 0);

            return null;
        }
        Cache::put($budgetKey, $used + 1, now()->addDay());

        $started = hrtime(true);
        $result = $this->gemini->json($prompt);
        $latency = (int) round((hrtime(true) - $started) / 1_000_000);
        if ($result === null) {
            $failKey = 'nightlife:ai:failures:'.$feature;
            $failures = (int) Cache::get($failKey, 0) + 1;
            Cache::put($failKey, $failures, now()->addMinutes(30));
            if ($failures >= (int) config('nightlife.ai_circuit_failures', 3)) {
                Cache::put($circuitKey, true, now()->addMinutes((int) config('nightlife.ai_circuit_minutes', 10)));
                Cache::forget($failKey);
            }
            $this->log($feature, $promptVersion, 'fallback', $latency);

            return null;
        }

        Cache::forget('nightlife:ai:failures:'.$feature);
        $this->log($feature, $promptVersion, 'success', $latency);

        return $result;
    }

    public function usageSummary(): array
    {
        $rows = AiTaskUsage::recent()->get();

        return [
            'total' => $rows->count(),
            'success' => $rows->where('status', 'success')->count(),
            'fallback' => $rows->whereIn('status', ['fallback', 'circuit_open', 'budget_exceeded', 'disabled'])->count(),
            'average_latency_ms' => (int) round((float) $rows->avg('latency_ms')),
        ];
    }

    private function log(string $feature, string $promptVersion, string $status, int $latency): void
    {
        AiTaskUsage::create([
            'feature' => $feature,
            'prompt_version' => $promptVersion,
            'status' => $status,
            'latency_ms' => $latency,
            'model' => config('services.gemini.model'),
        ]);
    }
}
