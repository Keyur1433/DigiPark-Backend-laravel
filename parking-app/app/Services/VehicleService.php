<?php

namespace App\Services;

use App\Models\Vehicle;

class VehicleService
{
    /**
     * Create a new vehicle
     */
    public function createVehicle(int $userId, array $data): Vehicle
    {
        return Vehicle::create([
            'user_id' => $userId,
            'type' => $data['type'],
            'number_plate' => $data['number_plate'],
            'brand' => $data['brand'] ?? null,
            'model' => $data['model'] ?? null,
            'color' => $data['color'] ?? null,
        ]);
    }

    /**
     * Update a vehicle
     */
    public function updateVehicle(Vehicle $vehicle, array $data): Vehicle
    {
        $vehicle->update([
            'type' => $data['type'],
            'number_plate' => $data['number_plate'],
            'brand' => $data['brand'] ?? $vehicle->brand,
            'model' => $data['model'] ?? $vehicle->model,
            'color' => $data['color'] ?? $vehicle->color,
        ]);

        return $vehicle;
    }

    /**
     * Delete a vehicle
     */
    public function deleteVehicle(Vehicle $vehicle): bool
    {
        // Check if the vehicle is currently being used in an active booking
        $activeBooking = $vehicle->bookings()
            ->whereIn('status', ['upcoming', 'checked_in'])
            ->first();

        if ($activeBooking) {
            throw new \Exception('Cannot delete vehicle with active bookings.');
        }

        return $vehicle->delete();
    }

    /**
     * Get user's vehicles
     */
    public function getUserVehicles(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return Vehicle::where('user_id', $userId)->get();
    }
}
