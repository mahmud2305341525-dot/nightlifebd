<?php

namespace App\Contracts;

use App\Models\EventBooking;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function createPending(EventBooking $booking): array;

    public function handleWebhook(Request $request): array;
}
