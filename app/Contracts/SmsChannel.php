<?php

namespace App\Contracts;

interface SmsChannel
{
    public function send(string $phone, string $message): array;
}
