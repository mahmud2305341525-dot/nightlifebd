<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Allow anonymization on reports when user deletes their account
        Schema::table('reports', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        // 2. Stale trip alert tracking
        Schema::table('trips', function (Blueprint $table) {
            $table->timestamp('stale_alerted_at')->nullable()->after('last_ping_at');
        });

        // 3. Venue Right-of-Reply
        Schema::create('report_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('response');
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->string('moderation_note', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 4. Abuse Reporting (Flagging community reports)
        Schema::create('report_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 50); // spam, false_information, harassment, offensive, other
            $table->text('details')->nullable();
            $table->string('status', 20)->default('pending'); // pending, resolved, dismissed
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_taken', 100)->nullable();
            $table->timestamps();

            $table->unique(['report_id', 'user_id'], 'report_user_flag_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_flags');
        Schema::dropIfExists('report_responses');

        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('stale_alerted_at');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
