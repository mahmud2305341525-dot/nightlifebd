<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around the Google Gemini free-tier REST API.
 * Every method returns null on failure so callers can fall back gracefully.
 */
class GeminiService
{
    private const BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function enabled(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /** Ask the model for a JSON answer and decode it. */
    public function json(string $prompt): ?array
    {
        if (! $this->enabled()) {
            return null;
        }
        try {
            $res = Http::timeout(30)
                ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
                ->post(self::BASE.config('services.gemini.model').':generateContent', [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.2],
                ]);
            if (! $res->successful()) {
                Log::warning('Gemini generateContent failed', ['status' => $res->status()]);

                return null;
            }

            return $this->decode($res->json('candidates.0.content.parts.0.text'));
        } catch (\Throwable $e) {
            Log::warning('Gemini error: '.$e->getMessage());

            return null;
        }
    }

    /** Text embedding (256 dimensions). */
    public function embed(string $text): ?array
    {
        if (! $this->enabled()) {
            return null;
        }
        try {
            $res = Http::timeout(20)
                ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
                ->post(self::BASE.config('services.gemini.embed_model').':embedContent', [
                    'content' => ['parts' => [['text' => $text]]],
                    'outputDimensionality' => 256,
                ]);
            $values = $res->successful() ? $res->json('embedding.values') : null;

            return is_array($values) && count($values) > 0 ? array_map('floatval', $values) : null;
        } catch (\Throwable $e) {
            Log::warning('Gemini embed error: '.$e->getMessage());

            return null;
        }
    }

    public function decode(?string $text): ?array
    {
        if (! $text) {
            return null;
        }
        $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text)));
        $data = json_decode($text, true);

        return is_array($data) ? $data : null;
    }
}
