<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_scores', function (Blueprint $table) {
            $table->string('confidence_level', 12)->default('low')->after('badge');
            $table->unsignedSmallInteger('evidence_count')->default(0)->after('report_count');
            $table->text('confidence_explanation')->nullable()->after('explanation');
        });
    }

    public function down(): void
    {
        Schema::table('venue_scores', function (Blueprint $table) {
            $table->dropColumn(['confidence_level', 'evidence_count', 'confidence_explanation']);
        });
    }
};
