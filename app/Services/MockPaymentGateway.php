<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\EventBooking;
use App\Models\PaymentEvent;
use Illuminate\Http\Request;

class MockPaymentGateway implements PaymentGateway
{
    public function createPending(EventBooking $booking): array
    {
        $booking->update([
            'payment_status' => 'pending',
            'status' => 'pending_payment',
            'payment_expires_at' => now()->addMinutes(10),
            'provider_reference' => 'MOCK-'.$booking->booking_reference,
        ]);

        return [
            'status' => 'pending_payment',
            'provider_reference' => $booking->provider_reference,
            'confirm_url' => route('payments.mock.webhook'),
        ];
    }

    public function handleWebhook(Request $request): array
    {
        $reference = (string) $request->input('provider_reference');
        $booking = EventBooking::where('provider_reference', $reference)
            ->orWhere('booking_reference', $reference)
            ->first();
        if (! $booking) {
            return ['status' => 'rejected', 'message' => 'Unknown booking'];
        }

        $signature = (string) $request->header('X-NightLifeBD-Mock-Signature');
        $calculated = hash_hmac('sha256', (string) $booking->provider_reference, (string) config('app.key'));
        $verified = $signature !== '' && hash_equals($calculated, $signature);

        PaymentEvent::create([
            'event_booking_id' => $booking->id,
            'provider' => 'mock',
            'event_type' => 'payment.confirmed',
            'provider_reference' => $reference,
            'signature_status' => $verified ? 'verified' : 'unverified',
            'amount' => $booking->total_price,
            'currency' => $booking->currency,
            'payload' => $request->all(),
        ]);
        if (! $verified) {
            return ['status' => 'rejected', 'message' => 'Signature not verified'];
        }

        if ($booking->payment_status !== 'paid') {
            $booking->update([
                'payment_status' => 'paid',
                'status' => 'confirmed',
                'webhook_verified_at' => now(),
            ]);
        }

        return ['status' => 'confirmed', 'booking_reference' => $booking->booking_reference];
    }
}
