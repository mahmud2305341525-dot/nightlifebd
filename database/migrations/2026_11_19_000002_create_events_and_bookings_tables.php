<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Events Table
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('slug', 180)->unique();
            $table->string('category', 50)->default('concert'); // concert, live_music, festival, night_market, cultural
            $table->text('description');
            $table->string('banner_path')->nullable();
            $table->string('area', 100); // e.g. Bashundhara, Dhanmondi, Gulshan, Banani, Uttara
            $table->string('address', 255);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 30)->default('published'); // draft, published, cancelled, completed
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['starts_at', 'status']);
            $table->index(['area', 'status']);
            $table->index('category');
        });

        // 2. Event Ticket Tiers
        Schema::create('event_ticket_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name', 100); // General Admission, VIP, Early Bird, Student
            $table->decimal('price', 10, 2)->default(0.00);
            $table->unsignedInteger('capacity')->default(100);
            $table->unsignedInteger('booked_count')->default(0);
            $table->unsignedInteger('max_per_user')->default(5);
            $table->text('perks')->nullable(); // JSON or text description of perks
            $table->timestamps();
        });

        // 3. Event Bookings
        Schema::create('event_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_reference', 40)->unique(); // e.g. NL-EVT-83921
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('ticket_tier_id')->constrained('event_ticket_tiers')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0.00);
            $table->decimal('total_price', 10, 2)->default(0.00);
            $table->string('payment_method', 40)->default('free'); // free, mock_bkash, mock_nagad, mock_card
            $table->string('payment_status', 30)->default('paid'); // paid, pending, refunded
            $table->string('qr_token', 64)->unique();
            $table->string('status', 30)->default('confirmed'); // confirmed, checked_in, cancelled
            $table->dateTime('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['event_id', 'status']);
        });

        // 4. In-App Event Notifications
        Schema::create('event_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('message', 255);
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_notifications');
        Schema::dropIfExists('event_bookings');
        Schema::dropIfExists('event_ticket_tiers');
        Schema::dropIfExists('events');
    }
};
