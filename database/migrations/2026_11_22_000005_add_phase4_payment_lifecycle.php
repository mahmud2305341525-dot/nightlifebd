<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_bookings', function (Blueprint $table) {
            $table->string('currency', 3)->default('BDT');
            $table->string('idempotency_key', 80)->nullable()->unique();
            $table->string('provider_reference', 255)->nullable()->index();
            $table->timestamp('payment_expires_at')->nullable()->index();
            $table->timestamp('webhook_verified_at')->nullable();
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_booking_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('event_type', 50);
            $table->string('provider_reference', 255)->nullable();
            $table->string('signature_status', 20)->default('unverified');
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::table('event_bookings', fn (Blueprint $table) => $table->dropColumn([
            'currency', 'idempotency_key', 'provider_reference', 'payment_expires_at', 'webhook_verified_at',
        ]));
    }
};
