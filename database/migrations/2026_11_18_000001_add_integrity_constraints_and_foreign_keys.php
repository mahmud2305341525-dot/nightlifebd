<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add SoftDeletes to reports table if not already present
        if (! Schema::hasColumn('reports', 'deleted_at')) {
            Schema::table('reports', function (Blueprint $table) {
                $table->softDeletes()->after('updated_at');
            });
        }

        // 2. Add self-referencing foreign key on duplicate_of with restrictOnDelete
        // Restrict prevents deleting a parent report while child duplicates reference it,
        // ensuring duplicate corroborating community reports are preserved and never
        // accidentally converted into independent incidents.
        Schema::table('reports', function (Blueprint $table) {
            $table->foreign('duplicate_of')
                ->references('id')
                ->on('reports')
                ->restrictOnDelete();
        });

        // 3. Add CHECK constraints for drivers that support them (e.g. MySQL, PostgreSQL)
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE reports ADD CONSTRAINT chk_reports_severity CHECK (severity IS NULL OR (severity >= 1 AND severity <= 5))');
            DB::statement('ALTER TABLE reports ADD CONSTRAINT chk_reports_rating CHECK (rating IS NULL OR (rating >= 1 AND rating <= 5))');
            DB::statement('ALTER TABLE venues ADD CONSTRAINT chk_venues_lighting CHECK (lighting >= 1 AND lighting <= 5)');
            DB::statement('ALTER TABLE venues ADD CONSTRAINT chk_venues_price_tier CHECK (price_tier >= 1 AND price_tier <= 3)');
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            try { DB::statement('ALTER TABLE reports DROP CHECK chk_reports_severity'); } catch (\Throwable) {}
            try { DB::statement('ALTER TABLE reports DROP CHECK chk_reports_rating'); } catch (\Throwable) {}
            try { DB::statement('ALTER TABLE venues DROP CHECK chk_venues_lighting'); } catch (\Throwable) {}
            try { DB::statement('ALTER TABLE venues DROP CHECK chk_venues_price_tier'); } catch (\Throwable) {}
        }

        Schema::table('reports', function (Blueprint $table) {
            $table->dropForeign(['duplicate_of']);
            $table->dropSoftDeletes();
        });
    }
};
