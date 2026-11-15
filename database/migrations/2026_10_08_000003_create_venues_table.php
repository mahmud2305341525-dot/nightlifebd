<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('category', 20);               // cafe, rooftop, restaurant, eatery, event, lounge
            $table->string('area', 60)->index();
            $table->unsignedTinyInteger('price_tier')->default(2);   // 1 budget, 2 mid, 3 high
            $table->text('description')->nullable();
            $table->string('vibe_tags')->nullable();       // comma separated: quiet, family, music, view ...
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('is_restricted')->default(false);
            $table->boolean('has_cctv')->default(false);
            $table->boolean('has_guard')->default(false);
            $table->unsignedTinyInteger('lighting')->default(3);     // 1-5
            $table->unsignedTinyInteger('safety_score')->nullable(); // snapshot of the night bucket
            $table->string('badge', 12)->default('unrated');
            $table->string('status', 12)->default('pending')->index();
            $table->json('coach_tips')->nullable();
            $table->timestamp('coach_at')->nullable();
            $table->timestamps();
        });

        Schema::create('venue_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('bucket', 10);
            $table->unsignedTinyInteger('score');
            $table->string('badge', 12);
            $table->text('explanation')->nullable();
            $table->unsignedSmallInteger('report_count')->default(0);
            $table->timestamps();
            $table->unique(['venue_id', 'bucket']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_scores');
        Schema::dropIfExists('venues');
    }
};
