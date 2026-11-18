<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('ai_category', 20)->nullable()->after('category');
            $table->unsignedTinyInteger('ai_severity')->nullable()->after('severity');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['ai_category', 'ai_severity']);
        });
    }
};
