<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emergency_contacts', function (Blueprint $table) {
            $table->string('verification_status', 20)->default('unverified')->index();
            $table->string('telegram_verification_token', 64)->nullable()->unique();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_reachable_at')->nullable();
        });

        Schema::create('alert_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('emergency_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20)->index();
            $table->string('provider_message_id', 255)->nullable();
            $table->string('status', 30)->index();
            $table->text('error')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->boolean('low_confidence')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('trips', fn (Blueprint $table) => $table->dropColumn('low_confidence'));
        Schema::dropIfExists('alert_deliveries');
        Schema::table('emergency_contacts', fn (Blueprint $table) => $table->dropColumn([
            'verification_status', 'telegram_verification_token', 'verified_at', 'last_reachable_at',
        ]));
    }
};
