<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add composite indexes that are hit on every page load.
     * - (venue_id, status)    → SafetyScoreService, venue show, report flow
     * - (status, lat, lng)    → RouteService riskData bbox filter (pushed to SQL)
     * - (status, area)        → Venue::approved() + area filter
     */
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            // Used by SafetyScoreService::reportsFor() and RouteService::riskData()
            $table->index(['venue_id', 'status'], 'reports_venue_status_idx');
            // Used by RouteService::riskData() bbox query (SQL-level bbox filter)
            $table->index(['status', 'lat', 'lng'], 'reports_status_lat_lng_idx');
        });

        // venue_scores already has unique(['venue_id','bucket']) which acts as a covering index.
        // Add a status+area composite index on venues for the approved() + area filter scope.
        Schema::table('venues', function (Blueprint $table) {
            $table->index(['status', 'area'], 'venues_status_area_idx');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex('reports_venue_status_idx');
            $table->dropIndex('reports_status_lat_lng_idx');
        });

        Schema::table('venues', function (Blueprint $table) {
            $table->dropIndex('venues_status_area_idx');
        });
    }
};
