<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParkingLocation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'owner_id',
        'name',
        'address',
        'city',
        'state',
        'country',
        'zip_code',
        'latitude',
        'longitude',
        'two_wheeler_capacity',
        'four_wheeler_capacity',
        'two_wheeler_hourly_rate',
        'four_wheeler_hourly_rate',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'two_wheeler_hourly_rate' => 'decimal:2',
        'four_wheeler_hourly_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the owner of the parking location.
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the slot availabilities for the parking location.
     */
    public function slotAvailabilities()
    {
        return $this->hasMany(ParkingSlotAvailability::class);
    }

    /**
     * Get the bookings for the parking location.
     */
    public function bookings()
    {
        return $this->hasMany(ParkingBooking::class);
    }

    /**
     * Get the time slots for the parking location.
     */
    public function timeSlots()
    {
        return $this->hasMany(ParkingTimeSlot::class);
    }
}
