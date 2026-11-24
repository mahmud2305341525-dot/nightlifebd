<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_task_usages', function (Blueprint $table) {
            $table->id();
            $table->string('feature', 50)->index();
            $table->string('prompt_version', 50)->default('unknown');
            $table->string('status', 20)->index();
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('model', 100)->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->string('ai_suggested_category', 20)->nullable();
            $table->unsignedTinyInteger('ai_suggested_severity')->nullable();
            $table->text('ai_suggested_rationale')->nullable();
            $table->boolean('pii_scrubbed')->default(false);
            $table->boolean('defamation_flagged')->default(false);
        });

        Schema::table('report_photos', function (Blueprint $table) {
            $table->string('ai_relevance', 20)->nullable();
            $table->boolean('ai_faces_blur_suggested')->default(false);
            $table->boolean('ai_explicit_flagged')->default(false);
            $table->text('ai_advice')->nullable();
        });

        Schema::table('dark_spots', function (Blueprint $table) {
            $table->string('status', 20)->default('community')->index();
            $table->timestamp('moderator_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dark_spots', fn (Blueprint $table) => $table->dropColumn(['status', 'moderator_confirmed_at']));
        Schema::table('report_photos', fn (Blueprint $table) => $table->dropColumn([
            'ai_relevance', 'ai_faces_blur_suggested', 'ai_explicit_flagged', 'ai_advice',
        ]));
        Schema::table('reports', fn (Blueprint $table) => $table->dropColumn([
            'ai_suggested_category', 'ai_suggested_severity', 'ai_suggested_rationale', 'pii_scrubbed', 'defamation_flagged',
        ]));
        Schema::dropIfExists('ai_task_usages');
    }
};
