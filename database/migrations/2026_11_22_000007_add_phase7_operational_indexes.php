<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->index(['status', 'incident_time'], 'reports_status_incident_idx');
            $table->index(['lat', 'lng', 'incident_time'], 'reports_geo_time_idx');
        });
        Schema::table('safe_havens', function (Blueprint $table) {
            $table->index(['lat', 'lng', 'type'], 'havens_geo_type_idx');
        });
        Schema::table('trips', function (Blueprint $table) {
            $table->index(['user_id', 'expires_at', 'ended_at'], 'trips_active_user_idx');
        });
        Schema::table('dark_spots', function (Blueprint $table) {
            $table->index(['lat', 'lng', 'status'], 'dark_spots_geo_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('dark_spots', fn (Blueprint $table) => $table->dropIndex('dark_spots_geo_status_idx'));
        Schema::table('trips', fn (Blueprint $table) => $table->dropIndex('trips_active_user_idx'));
        Schema::table('safe_havens', fn (Blueprint $table) => $table->dropIndex('havens_geo_type_idx'));
        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex('reports_status_incident_idx');
            $table->dropIndex('reports_geo_time_idx');
        });
    }
};
