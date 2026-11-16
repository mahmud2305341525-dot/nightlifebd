<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 11)->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('city', 60)->nullable();
            $table->boolean('is_adult_confirmed')->default(false);
            $table->decimal('trust_score', 3, 2)->default(0.50);
            $table->string('telegram_chat_id', 40)->nullable();
            $table->string('locale', 2)->default('en');
            $table->boolean('is_banned')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'gender', 'city', 'is_adult_confirmed', 'trust_score', 'telegram_chat_id', 'locale', 'is_banned']);
        });
    }
};
