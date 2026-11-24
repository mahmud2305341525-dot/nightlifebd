<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->string('source', 30)->nullable()->index();
            $table->string('source_id', 100)->nullable()->index();
            $table->string('source_url', 255)->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->string('verification_level', 30)->default('unverified')->index();
            $table->boolean('has_cctv')->nullable()->change();
            $table->boolean('has_guard')->nullable()->change();
            $table->unsignedTinyInteger('lighting')->nullable()->change();
        });

        Schema::table('safe_havens', function (Blueprint $table) {
            $table->string('source', 30)->default('seed_unverified')->index();
            $table->timestamp('last_verified_at')->nullable();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('submission_status', 30)->default('approved')->index();
        });

        Schema::create('venue_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('evidence_path')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 190)->nullable();
            $table->text('note')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['venue_id', 'user_id']);
        });

        Schema::create('evidence_candidates', function (Blueprint $table) {
            $table->id();
            $table->string('source_url', 255)->nullable();
            $table->string('category', 30);
            $table->string('approximate_place', 255)->nullable();
            $table->string('approximate_area', 100)->nullable();
            $table->timestamp('approximate_time')->nullable();
            $table->text('facts');
            $table->string('status', 20)->default('unverified')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_candidates');
        Schema::dropIfExists('venue_claims');
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('submission_status'));
        Schema::table('safe_havens', fn (Blueprint $table) => $table->dropColumn(['source', 'last_verified_at']));
        Schema::table('venues', fn (Blueprint $table) => $table->dropColumn([
            'source', 'source_id', 'source_url', 'imported_at', 'verification_level',
        ]));
    }
};
