<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safe_havens', function (Blueprint $table) {
            $table->id();
            $table->string('osm_id', 30)->nullable()->unique();
            $table->string('type', 20)->index();   // police | hospital | pharmacy
            $table->string('name');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('phone', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('dark_spots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('note', 200)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dark_spots');
        Schema::dropIfExists('safe_havens');
    }
};
