<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\EventBooking;
use Illuminate\Http\Request;

class NonePaymentGateway implements PaymentGateway
{
    public function createPending(EventBooking $booking): array
    {
        return [
            'status' => 'ticketing_not_enabled',
            'message' => 'Paid ticketing is not enabled. Free events can still be booked.',
        ];
    }

    public function handleWebhook(Request $request): array
    {
        return ['status' => 'rejected', 'message' => 'No payment gateway configured'];
    }
}
