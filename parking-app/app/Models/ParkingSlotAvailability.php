<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParkingSlotAvailability extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'parking_location_id',
        'vehicle_type',
        'available_slots',
        'total_slots',
    ];

    /**
     * Get the parking location that owns the slot availability.
     */
    public function parkingLocation()
    {
        return $this->belongsTo(ParkingLocation::class);
    }
}
