<?php

namespace App\Services;

use App\Contracts\SmsChannel;
use App\Models\AlertDelivery;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emergency Alert Dispatcher.
 * Dispatches emergency alerts across electronic channels (Telegram Bot API, Email)
 * with strict delivery guarantees:
 *  - Distinguishes between confirmed delivery, local development logging, and failures.
 *  - Never claims an alert was delivered to a user when only queued or written to local logs.
 *  - Handles channel timeouts and failures gracefully without crashing emergency workflows.
 */
class AlertService
{
    public function __construct(private SmsChannel $sms) {}

    /**
     * @return array{
     *     contacts_count: int,
     *     contacts: int,
     *     email: int,
     *     telegram: int,
     *     delivery: array{
     *         telegram: array{status: string, attempted: int, confirmed: int, failed: int, message: ?string},
     *         email: array{status: string, attempted: int, confirmed: int, failed: int, is_local_log: bool, message: ?string}
     *     },
     *     summary: string,
     *     is_simulation: bool
     * }
     */
    public function notifyContacts(User $user, string $subject, string $message): array
    {
        $contacts = $user->emergencyContacts;
        $contactsCount = $contacts->count();

        $telegramResult = [
            'status' => 'not_configured',
            'attempted' => 0,
            'confirmed' => 0,
            'failed' => 0,
            'message' => null,
        ];
        $smsResult = [
            'status' => 'not_configured',
            'attempted' => 0,
            'confirmed' => 0,
            'failed' => 0,
            'message' => null,
        ];

        $isLocalMail = (config('mail.default') === 'log');
        $emailResult = [
            'status' => 'not_configured',
            'attempted' => 0,
            'confirmed' => 0,
            'failed' => 0,
            'is_local_log' => $isLocalMail,
            'message' => null,
        ];

        if ($contactsCount === 0) {
            return [
                'contacts_count' => 0,
                'contacts' => 0,
                'email' => 0,
                'telegram' => 0,
                'delivery' => [
                    'telegram' => $telegramResult,
                    'email' => $emailResult,
                    'sms' => $smsResult,
                ],
                'summary' => __('No emergency contacts configured.'),
                'is_simulation' => false,
            ];
        }

        $botToken = config('services.telegram.token');

        foreach ($contacts as $c) {
            // 1. Email notification
            if (filled($c->email)) {
                $emailResult['attempted']++;
                try {
                    Mail::raw($message, fn ($m) => $m->to($c->email)->subject($subject));
                    $emailResult['confirmed']++;
                    AlertDelivery::create([
                        'user_id' => $user->id,
                        'emergency_contact_id' => $c->id,
                        'channel' => 'email',
                        'status' => $isLocalMail ? 'logged_local' : 'confirmed',
                        'attempted_at' => now(),
                        'confirmed_at' => $isLocalMail ? null : now(),
                    ]);
                } catch (\Throwable $e) {
                    $emailResult['failed']++;
                    AlertDelivery::create([
                        'user_id' => $user->id,
                        'emergency_contact_id' => $c->id,
                        'channel' => 'email',
                        'status' => 'failed',
                        'error' => mb_substr($e->getMessage(), 0, 1000),
                        'attempted_at' => now(),
                    ]);
                    Log::warning('Alert email failed: '.$e->getMessage());
                }
            }

            // 2. Telegram Bot API notification
            if (filled($c->telegram_chat_id)) {
                if (filled($botToken)) {
                    $telegramResult['attempted']++;
                    try {
                        $res = Http::timeout(8)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                            'chat_id' => $c->telegram_chat_id,
                            'text' => $subject."\n\n".$message,
                        ]);
                        if ($res->successful() && ($res->json('ok') === true)) {
                            $telegramResult['confirmed']++;
                            AlertDelivery::create([
                                'user_id' => $user->id,
                                'emergency_contact_id' => $c->id,
                                'channel' => 'telegram',
                                'provider_message_id' => is_numeric($res->json('result.message_id')) ? (string) $res->json('result.message_id') : null,
                                'status' => 'confirmed',
                                'attempted_at' => now(),
                                'confirmed_at' => now(),
                            ]);
                        } else {
                            $telegramResult['failed']++;
                            AlertDelivery::create([
                                'user_id' => $user->id,
                                'emergency_contact_id' => $c->id,
                                'channel' => 'telegram',
                                'status' => 'failed',
                                'error' => 'HTTP '.$res->status(),
                                'attempted_at' => now(),
                            ]);
                            Log::warning('Alert Telegram rejected by API: '.$res->status());
                        }
                    } catch (\Throwable $e) {
                        $telegramResult['failed']++;
                        Log::warning('Alert Telegram connection failed: '.$e->getMessage());
                        AlertDelivery::create([
                            'user_id' => $user->id,
                            'emergency_contact_id' => $c->id,
                            'channel' => 'telegram',
                            'status' => 'failed',
                            'error' => mb_substr($e->getMessage(), 0, 1000),
                            'attempted_at' => now(),
                        ]);
                    }
                } else {
                    $telegramResult['status'] = 'token_missing';
                }
            }
        }

        foreach ($contacts as $c) {
            if (! filled($c->phone)) {
                continue;
            }
            $smsResult['attempted']++;
            $sms = $this->sms->send($c->phone, $subject."\n\n".$message);
            if ($sms['status'] === 'accepted') {
                $smsResult['confirmed']++;
                $smsResult['status'] = 'accepted';
                AlertDelivery::create([
                    'user_id' => $user->id,
                    'emergency_contact_id' => $c->id,
                    'channel' => 'sms',
                    'provider_message_id' => $sms['provider_message_id'] ?: null,
                    'status' => 'accepted',
                    'attempted_at' => now(),
                ]);
            } elseif ($sms['status'] === 'not_configured') {
                $smsResult['status'] = 'not_configured';
                $smsResult['message'] = __('SMS provider is not configured.');
            } else {
                $smsResult['failed']++;
                $smsResult['status'] = 'failed';
                AlertDelivery::create([
                    'user_id' => $user->id,
                    'emergency_contact_id' => $c->id,
                    'channel' => 'sms',
                    'status' => 'failed',
                    'error' => $sms['error'] ?? null,
                    'attempted_at' => now(),
                ]);
            }
        }

        // Finalize Email channel status
        if ($emailResult['attempted'] > 0) {
            if ($emailResult['is_local_log']) {
                $emailResult['status'] = 'logged_local';
                $emailResult['message'] = __('Email logged locally (dev mail driver).');
            } elseif ($emailResult['confirmed'] > 0) {
                $emailResult['status'] = 'sent';
                $emailResult['message'] = __('Email dispatched to mail server.');
            } else {
                $emailResult['status'] = 'failed';
                $emailResult['message'] = __('Email dispatch failed.');
            }
        }

        // Finalize Telegram channel status
        if ($telegramResult['attempted'] > 0) {
            if ($telegramResult['confirmed'] > 0 && $telegramResult['failed'] === 0) {
                $telegramResult['status'] = 'confirmed';
                $telegramResult['message'] = __('Telegram alert confirmed by Telegram Bot API.');
            } elseif ($telegramResult['confirmed'] > 0 && $telegramResult['failed'] > 0) {
                $telegramResult['status'] = 'partial';
                $telegramResult['message'] = __('Telegram alert partially delivered.');
            } else {
                $telegramResult['status'] = 'failed';
                $telegramResult['message'] = __('Telegram delivery failed.');
            }
        }

        $isSimulation = ($isLocalMail && $telegramResult['confirmed'] === 0 && $smsResult['confirmed'] === 0);

        // Build human-readable status summary
        $parts = [];
        if ($telegramResult['confirmed'] > 0) {
            $parts[] = __('Telegram: :n confirmed', ['n' => $telegramResult['confirmed']]);
        }
        if ($emailResult['confirmed'] > 0) {
            $parts[] = $isLocalMail
                ? __('Email: :n logged locally (dev mode)', ['n' => $emailResult['confirmed']])
                : __('Email: :n sent', ['n' => $emailResult['confirmed']]);
        }
        if ($smsResult['confirmed'] > 0) {
            $parts[] = __('SMS: :n accepted', ['n' => $smsResult['confirmed']]);
        }
        if ($telegramResult['failed'] > 0 || $emailResult['failed'] > 0 || $smsResult['failed'] > 0) {
            $parts[] = __('Failed: :n', ['n' => ($telegramResult['failed'] + $emailResult['failed'] + $smsResult['failed'])]);
        }

        $summary = ! empty($parts)
            ? implode(' · ', $parts)
            : __('No electronic channels configured. Use direct SMS, WhatsApp, or 999.');

        return [
            'contacts_count' => $contactsCount,
            'contacts' => $contactsCount,
            'email' => $emailResult['confirmed'],
            'telegram' => $telegramResult['confirmed'],
            'delivery' => [
                'telegram' => $telegramResult,
                'email' => $emailResult,
                'sms' => $smsResult,
            ],
            'summary' => $summary,
            'is_simulation' => $isSimulation,
        ];
    }
}
