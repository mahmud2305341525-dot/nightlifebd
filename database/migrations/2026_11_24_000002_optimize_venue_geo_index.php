<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $existing = array_column(Schema::getIndexes('venues'), 'name');

        if (in_array('venues_status_geo_idx', $existing, true)) {
            Schema::table('venues', function (Blueprint $table) {
                $table->dropIndex('venues_status_geo_idx');
            });
        }

        if (! in_array('venues_geo_idx', $existing, true)) {
            Schema::table('venues', function (Blueprint $table) {
                $table->index(['lat', 'lng'], 'venues_geo_idx');
            });
        }
    }

    public function down(): void
    {
        $existing = array_column(Schema::getIndexes('venues'), 'name');

        if (in_array('venues_geo_idx', $existing, true)) {
            Schema::table('venues', function (Blueprint $table) {
                $table->dropIndex('venues_geo_idx');
            });
        }

        if (! in_array('venues_status_geo_idx', $existing, true)) {
            Schema::table('venues', function (Blueprint $table) {
                $table->index(['status', 'lat', 'lng'], 'venues_status_geo_idx');
            });
        }
    }
};
