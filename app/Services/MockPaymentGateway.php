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

        if (! $verified) {
            PaymentEvent::create([
                'event_booking_id' => $booking->id,
                'provider' => 'mock',
                'event_type' => 'payment.failed',
                'provider_reference' => $reference,
                'signature_status' => 'unverified',
                'amount' => $booking->total_price,
                'currency' => $booking->currency,
                'payload' => $request->all(),
            ]);

            return ['status' => 'rejected', 'message' => 'Signature not verified'];
        }

        // Amount verification: prevent paying lesser amount
        if ($request->filled('amount') && (float) $request->input('amount') < (float) $booking->total_price) {
            PaymentEvent::create([
                'event_booking_id' => $booking->id,
                'provider' => 'mock',
                'event_type' => 'payment.amount_mismatch',
                'provider_reference' => $reference,
                'signature_status' => 'verified',
                'amount' => (float) $request->input('amount'),
                'currency' => $booking->currency,
                'payload' => $request->all(),
            ]);

            return ['status' => 'rejected', 'message' => 'Payment amount mismatch'];
        }

        // Currency verification: prevent paying in wrong currency
        if ($request->filled('currency') && strtoupper((string) $request->input('currency')) !== strtoupper((string) $booking->currency)) {
            return ['status' => 'rejected', 'message' => 'Payment currency mismatch'];
        }

        // Expired/cancelled protection: do not revive dead or cancelled tickets
        if (in_array($booking->status, ['cancelled', 'expired'], true) || in_array($booking->payment_status, ['cancelled', 'expired', 'refund_pending', 'refunded'], true)) {
            PaymentEvent::create([
                'event_booking_id' => $booking->id,
                'provider' => 'mock',
                'event_type' => 'payment.rejected_late',
                'provider_reference' => $reference,
                'signature_status' => 'verified',
                'amount' => $booking->total_price,
                'currency' => $booking->currency,
                'payload' => $request->all(),
            ]);

            return ['status' => 'rejected', 'message' => 'Booking has expired or was cancelled'];
        }

        // Replay/idempotency protection: if already confirmed, return success without duplicate events
        if ($booking->payment_status === 'paid') {
            return ['status' => 'confirmed', 'message' => 'Already confirmed', 'booking_reference' => $booking->booking_reference];
        }

        PaymentEvent::create([
            'event_booking_id' => $booking->id,
            'provider' => 'mock',
            'event_type' => 'payment.confirmed',
            'provider_reference' => $reference,
            'signature_status' => 'verified',
            'amount' => $booking->total_price,
            'currency' => $booking->currency,
            'payload' => $request->all(),
        ]);

        $booking->update([
            'payment_status' => 'paid',
            'status' => 'confirmed',
            'webhook_verified_at' => now(),
        ]);

        return ['status' => 'confirmed', 'booking_reference' => $booking->booking_reference];
    }
}
