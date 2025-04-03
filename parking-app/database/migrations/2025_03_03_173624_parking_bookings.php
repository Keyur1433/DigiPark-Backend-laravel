<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('parking_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->foreignId('parking_location_id')->constrained()->onDelete('cascade');
            $table->enum('booking_type', ['check_in', 'advance']);
            $table->enum('status', ['upcoming', 'checked_in', 'completed', 'cancelled'])->default('upcoming');
            $table->timestamp('check_in_time');
            $table->timestamp('check_out_time');
            $table->integer('duration_hours');
            $table->decimal('amount', 8, 2);
            $table->string('qr_code')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parking_bookings');
    }
};
