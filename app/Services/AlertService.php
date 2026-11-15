<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends alerts to a user's emergency contacts by email and Telegram (both free).
 * Real SMS needs a paid gateway, so the browser SOS button uses a pre-filled sms: link instead.
 */
class AlertService
{
    /** @return array{email:int,telegram:int,contacts:int} */
    public function notifyContacts(User $user, string $subject, string $message): array
    {
        $sent = ['email' => 0, 'telegram' => 0, 'contacts' => 0];
        foreach ($user->emergencyContacts as $c) {
            $sent['contacts']++;
            if ($c->email) {
                try {
                    Mail::raw($message, fn ($m) => $m->to($c->email)->subject($subject));
                    $sent['email']++;
                } catch (\Throwable $e) {
                    Log::warning('Alert email failed: '.$e->getMessage());
                }
            }
            if ($c->telegram_chat_id && filled(config('services.telegram.token'))) {
                try {
                    $res = Http::timeout(10)->post('https://api.telegram.org/bot'.config('services.telegram.token').'/sendMessage', [
                        'chat_id' => $c->telegram_chat_id,
                        'text' => $subject."\n\n".$message,
                    ]);
                    if ($res->successful()) {
                        $sent['telegram']++;
                    }
                } catch (\Throwable $e) {
                    Log::warning('Alert telegram failed: '.$e->getMessage());
                }
            }
        }

        return $sent;
    }
}
