<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 12)->default('incident');          // incident | review
            $table->text('body');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->dateTime('incident_time');
            $table->boolean('is_anonymous')->default(false);
            $table->string('status', 12)->default('pending')->index(); // pending | verified | rejected

            // --- AI analysis (filled by ReportAnalyzer) ---
            $table->string('category', 20)->nullable();   // harassment, theft, violence, dark_road, overcharging, positive, other
            $table->unsignedTinyInteger('severity')->nullable();
            $table->string('language', 8)->nullable();    // bn | en | mixed
            $table->decimal('spam_probability', 3, 2)->nullable();
            $table->text('summary_en')->nullable();
            $table->json('embedding')->nullable();
            $table->string('embedding_source', 10)->nullable(); // gemini | local
            $table->unsignedBigInteger('duplicate_of')->nullable()->index();
            $table->string('analysis_source', 10)->nullable(); // gemini | keyword | seed
            $table->boolean('needs_review')->default(false);

            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
