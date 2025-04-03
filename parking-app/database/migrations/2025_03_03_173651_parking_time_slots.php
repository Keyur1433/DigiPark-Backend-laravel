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
        Schema::create('parking_time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_location_id')->constrained()->onDelete('cascade');
            $table->enum('vehicle_type', ['2-wheeler', '4-wheeler']);
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('available_slots');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parking_time_slots');
    }
};
