<?php

namespace App\Services;

use App\Contracts\SmsChannel;
use Illuminate\Support\Facades\Http;

class HttpSmsChannel implements SmsChannel
{
    public function send(string $phone, string $message): array
    {
        $endpoint = config('services.sms.endpoint');
        if (! filled($endpoint) || ! filled(config('services.sms.token'))) {
            return ['status' => 'not_configured', 'provider_message_id' => null, 'error' => 'SMS provider is not configured'];
        }

        $unicode = preg_match('/[^\x00-\x7F]/', $message) === 1;
        $segmentLimit = $unicode ? 70 : 160;
        $segments = max(1, (int) ceil(mb_strlen($message) / $segmentLimit));

        try {
            $response = Http::timeout(15)
                ->withToken((string) config('services.sms.token'))
                ->post($endpoint, [
                    'to' => $phone,
                    'text' => $message,
                    'sender' => config('services.sms.sender'),
                    'unicode' => $unicode,
                    'segments' => $segments,
                ]);
            if (! $response->successful()) {
                return ['status' => 'failed', 'provider_message_id' => null, 'error' => 'HTTP '.$response->status()];
            }

            return [
                'status' => 'accepted',
                'provider_message_id' => (string) ($response->json('message_id') ?? $response->json('id') ?? ''),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'provider_message_id' => null, 'error' => mb_substr($e->getMessage(), 0, 1000)];
        }
    }
}
