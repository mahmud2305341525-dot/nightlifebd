<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Feature: Venue Reviews & Ratings ---
        Schema::table('reports', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating')->nullable()->after('kind'); // 1-5 stars, only for kind=review
        });

        // --- Feature: Photo Evidence on Reports ---
        Schema::create('report_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->string('path');          // relative to private report_photos disk (storage/app/private/incident_photos)
            $table->string('thumbnail');     // resized thumbnail path on private report_photos disk
            $table->unsignedInteger('size_bytes')->default(0);
            $table->timestamp('created_at')->nullable();
        });

        // --- Feature: Venue Favorites (bonus, needed for dashboard quick access) ---
        Schema::create('venue_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'venue_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_favorites');
        Schema::dropIfExists('report_photos');
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('rating');
        });
    }
};
