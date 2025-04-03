<?php

namespace App\Services;

use App\Models\ParkingLocation;
use App\Models\ParkingSlotAvailability;
use Illuminate\Support\Facades\DB;

class ParkingLocationService
{
    /**
     * Create a new parking location
     */
    public function createParkingLocation(int $ownerId, array $data): ParkingLocation
    {
        DB::beginTransaction();

        try {
            // Create parking location
            $parkingLocation = ParkingLocation::create([
                'owner_id' => $ownerId,
                'name' => $data['name'],
                'address' => $data['address'],
                'city' => $data['city'],
                'state' => $data['state'],
                'country' => $data['country'],
                'zip_code' => $data['zip_code'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'two_wheeler_capacity' => $data['two_wheeler_capacity'],
                'four_wheeler_capacity' => $data['four_wheeler_capacity'],
                'two_wheeler_hourly_rate' => $data['two_wheeler_hourly_rate'],
                'four_wheeler_hourly_rate' => $data['four_wheeler_hourly_rate'],
                'is_active' => true,
            ]);

            // Create slot availabilities
            ParkingSlotAvailability::create([
                'parking_location_id' => $parkingLocation->id,
                'vehicle_type' => '2-wheeler',
                'available_slots' => $data['two_wheeler_capacity'],
                'total_slots' => $data['two_wheeler_capacity'],
            ]);

            ParkingSlotAvailability::create([
                'parking_location_id' => $parkingLocation->id,
                'vehicle_type' => '4-wheeler',
                'available_slots' => $data['four_wheeler_capacity'],
                'total_slots' => $data['four_wheeler_capacity'],
            ]);

            DB::commit();

            return $parkingLocation;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update a parking location
     */
    public function updateParkingLocation(ParkingLocation $parkingLocation, array $data): ParkingLocation
    {
        DB::beginTransaction();

        try {
            // Update basic information
            $parkingLocation->update([
                'name' => $data['name'],
                'address' => $data['address'],
                'city' => $data['city'],
                'state' => $data['state'],
                'country' => $data['country'],
                'zip_code' => $data['zip_code'] ?? $parkingLocation->zip_code,
                'latitude' => $data['latitude'] ?? $parkingLocation->latitude,
                'longitude' => $data['longitude'] ?? $parkingLocation->longitude,
                'two_wheeler_hourly_rate' => $data['two_wheeler_hourly_rate'],
                'four_wheeler_hourly_rate' => $data['four_wheeler_hourly_rate'],
            ]);

            // Update two-wheeler capacity if changed
            if ($parkingLocation->two_wheeler_capacity != $data['two_wheeler_capacity']) {
                $twoWheelerAvailability = $parkingLocation->slotAvailabilities()
                    ->where('vehicle_type', '2-wheeler')
                    ->first();

                $currentlyOccupied = $twoWheelerAvailability->total_slots - $twoWheelerAvailability->available_slots;

                if ($data['two_wheeler_capacity'] < $currentlyOccupied) {
                    throw new \Exception('Cannot reduce capacity below currently occupied slots.');
                }

                $parkingLocation->two_wheeler_capacity = $data['two_wheeler_capacity'];
                $parkingLocation->save();

                $twoWheelerAvailability->update([
                    'available_slots' => $data['two_wheeler_capacity'] - $currentlyOccupied,
                    'total_slots' => $data['two_wheeler_capacity'],
                ]);
            }

            // Update four-wheeler capacity if changed
            if ($parkingLocation->four_wheeler_capacity != $data['four_wheeler_capacity']) {
                $fourWheelerAvailability = $parkingLocation->slotAvailabilities()
                    ->where('vehicle_type', '4-wheeler')
                    ->first();

                $currentlyOccupied = $fourWheelerAvailability->total_slots - $fourWheelerAvailability->available_slots;

                if ($data['four_wheeler_capacity'] < $currentlyOccupied) {
                    throw new \Exception('Cannot reduce capacity below currently occupied slots.');
                }

                $parkingLocation->four_wheeler_capacity = $data['four_wheeler_capacity'];
                $parkingLocation->save();

                $fourWheelerAvailability->update([
                    'available_slots' => $data['four_wheeler_capacity'] - $currentlyOccupied,
                    'total_slots' => $data['four_wheeler_capacity'],
                ]);
            }

            DB::commit();

            return $parkingLocation;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Toggle parking location active status
     */
    public function toggleStatus(ParkingLocation $parkingLocation): bool
    {
        $parkingLocation->is_active = !$parkingLocation->is_active;
        $parkingLocation->save();

        return $parkingLocation->is_active;
    }

    /**
     * Search parking locations
     */
    public function searchParkingLocations(string $search = null, string $city = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = ParkingLocation::where('is_active', true);

        // Search by location name or address
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        // Filter by city
        if ($city) {
            $query->where('city', $city);
        }

        return $query->with('slotAvailabilities')->get();
    }
}
