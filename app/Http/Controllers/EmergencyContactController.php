<?php

namespace App\Http\Controllers;

use App\Models\EmergencyContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmergencyContactController extends Controller
{
    public function index(Request $request)
    {
        return view('contacts.index', ['contacts' => $request->user()->emergencyContacts]);
    }

    public function store(Request $request)
    {
        // Normalize input: strip non-digits for BD mobile phone normalization (+8801... / 8801... / 01...)
        $rawPhone = (string) $request->input('phone', '');
        $digits = preg_replace('/\D/', '', $rawPhone);
        if (str_starts_with($digits, '8801')) {
            $normalizedPhone = substr($digits, 2);
        } else {
            $normalizedPhone = $digits;
        }

        $request->merge([
            'phone' => $normalizedPhone,
            'name' => trim((string) $request->input('name')),
            'relation' => $request->filled('relation') ? trim((string) $request->input('relation')) : null,
            'email' => $request->filled('email') ? strtolower(trim((string) $request->input('email'))) : null,
            'telegram_chat_id' => $request->filled('telegram_chat_id') ? trim((string) $request->input('telegram_chat_id')) : null,
        ]);

        $userId = $request->user()->id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'phone' => [
                'required',
                'regex:/^01[3-9]\d{8}$/',
                Rule::unique('emergency_contacts', 'phone')->where('user_id', $userId),
            ],
            'relation' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'telegram_chat_id' => ['nullable', 'regex:/^-?\d{5,20}$/'],
        ], [
            'phone.regex' => __('Enter a valid 11-digit Bangladesh mobile number (e.g. 01712345678).'),
            'phone.unique' => __('You have already registered this phone number as an emergency contact.'),
            'telegram_chat_id.regex' => __('Telegram chat ID must be a number.'),
        ]);

        return DB::transaction(function () use ($request, $data) {
            if ($request->user()->emergencyContacts()->lockForUpdate()->count() >= 5) {
                return back()->withInput()->withErrors(['phone' => __('Maximum five emergency contacts allowed.')]);
            }

            $request->user()->emergencyContacts()->create(array_merge($data, [
                'verification_status' => 'unverified',
            ]));

            return back()->with('status', __('Contact saved.'));
        });
    }

    public function telegramLink(Request $request, EmergencyContact $contact)
    {
        abort_unless($contact->user_id === $request->user()->id, 403);
        $token = Str::random(48);
        $contact->update([
            'telegram_verification_token' => $token,
            'verification_status' => 'unverified',
        ]);
        $username = config('services.telegram.username');
        $link = filled($username)
            ? 'https://t.me/'.$username.'?start='.$token
            : null;

        return back()->with([
            'status' => $link ? __('Telegram verification link created.') : __('Telegram bot username is not configured.'),
            'telegram_link' => $link,
        ]);
    }

    public function telegramWebhook(Request $request)
    {
        $secret = config('services.telegram.webhook_secret');
        $headerSecret = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if (! filled($secret) || ! filled($headerSecret) || ! hash_equals((string) $secret, (string) $headerSecret)) {
            abort(403, 'Unauthorized webhook signature.');
        }
        $message = $request->input('message');
        $chatId = $message['chat']['id'] ?? null;
        $text = trim((string) ($message['text'] ?? ''));
        if (! is_numeric($chatId) || ! str_starts_with($text, '/start ')) {
            return response()->json(['ok' => true]);
        }
        $token = trim(substr($text, 7));
        $contact = EmergencyContact::where('telegram_verification_token', $token)->first();
        if (! $contact) {
            return response()->json(['ok' => true]);
        }
        $contact->update([
            'telegram_chat_id' => (string) $chatId,
            'verification_status' => 'verified',
            'verified_at' => now(),
            'last_reachable_at' => now(),
            'telegram_verification_token' => null,
        ]);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, EmergencyContact $contact)
    {
        abort_unless($contact->user_id === $request->user()->id, 403);
        $contact->delete();

        return back()->with('status', 'Contact removed.');
    }
}
