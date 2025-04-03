<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreParkingLocationRequest;
use App\Http\Resources\ParkingLocationResource;
use App\Models\ParkingLocation;
use App\Models\ParkingSlotAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class ParkingLocationController extends Controller
{
    /**
     * Display a listing of the parking locations.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ParkingLocation::query()->where('is_active', DB::raw('TRUE'));

        // Search by location name or address
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        // Filter by city
        if ($request->has('city')) {
            $query->where('city', $request->city);
        }

        $parkingLocations = $query->with('slotAvailabilities')->get();

        return response()->json([
            'parking_locations' => ParkingLocationResource::collection($parkingLocations),
        ]);
    }

    /**
     * Store a newly created parking location in storage.
     */
    public function store(StoreParkingLocationRequest $request): JsonResponse
    {
        // Check if user is an owner
        if (!Auth::user()->isOwner()) {
            return response()->json([
                'message' => 'Only parking owners can create parking locations.',
            ], 403);
        }

        DB::beginTransaction();

        try {
            $parkingLocation = ParkingLocation::create([
                'owner_id' => Auth::id(),
                'name' => $request->name,
                'address' => $request->address,
                'city' => $request->city,
                'state' => $request->state,
                'country' => $request->country,
                'zip_code' => $request->zip_code,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'two_wheeler_capacity' => $request->two_wheeler_capacity,
                'four_wheeler_capacity' => $request->four_wheeler_capacity,
                'two_wheeler_hourly_rate' => $request->two_wheeler_hourly_rate,
                'four_wheeler_hourly_rate' => $request->four_wheeler_hourly_rate,
                'is_active' => DB::raw('TRUE'),
            ]);

            // Create slot availabilities
            ParkingSlotAvailability::create([
                'parking_location_id' => $parkingLocation->id,
                'vehicle_type' => '2-wheeler',
                'available_slots' => $request->two_wheeler_capacity,
                'total_slots' => $request->two_wheeler_capacity,
            ]);

            ParkingSlotAvailability::create([
                'parking_location_id' => $parkingLocation->id,
                'vehicle_type' => '4-wheeler',
                'available_slots' => $request->four_wheeler_capacity,
                'total_slots' => $request->four_wheeler_capacity,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Parking location created successfully.',
                'parking_location' => new ParkingLocationResource($parkingLocation->load('slotAvailabilities')),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create parking location.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified parking location.
     */
    public function show(ParkingLocation $parkingLocation): JsonResponse
    {
        return response()->json([
            'parking_location' => new ParkingLocationResource($parkingLocation->load('slotAvailabilities')),
        ]);
    }

    /**
     * Update the specified parking location in storage.
     */
    public function update(StoreParkingLocationRequest $request, ParkingLocation $parkingLocation): JsonResponse
    {
        // Check if the parking location belongs to the authenticated owner
        if ($parkingLocation->owner_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        DB::beginTransaction();

        try {
            $parkingLocation->update([
                'name' => $request->name,
                'address' => $request->address,
                'city' => $request->city,
                'state' => $request->state,
                'country' => $request->country,
                'zip_code' => $request->zip_code,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'two_wheeler_hourly_rate' => $request->two_wheeler_hourly_rate,
                'four_wheeler_hourly_rate' => $request->four_wheeler_hourly_rate,
            ]);

            // Update two-wheeler capacity if changed
            if ($parkingLocation->two_wheeler_capacity != $request->two_wheeler_capacity) {
                $twoWheelerAvailability = $parkingLocation->slotAvailabilities()
                    ->where('vehicle_type', '2-wheeler')
                    ->first();

                $currentlyOccupied = $twoWheelerAvailability->total_slots - $twoWheelerAvailability->available_slots;

                if ($request->two_wheeler_capacity < $currentlyOccupied) {
                    throw new \Exception('Cannot reduce capacity below currently occupied slots.');
                }

                $parkingLocation->two_wheeler_capacity = $request->two_wheeler_capacity;
                $parkingLocation->save();

                $twoWheelerAvailability->update([
                    'available_slots' => $request->two_wheeler_capacity - $currentlyOccupied,
                    'total_slots' => $request->two_wheeler_capacity,
                ]);
            }

            // Update four-wheeler capacity if changed
            if ($parkingLocation->four_wheeler_capacity != $request->four_wheeler_capacity) {
                $fourWheelerAvailability = $parkingLocation->slotAvailabilities()
                    ->where('vehicle_type', '4-wheeler')
                    ->first();

                $currentlyOccupied = $fourWheelerAvailability->total_slots - $fourWheelerAvailability->available_slots;

                if ($request->four_wheeler_capacity < $currentlyOccupied) {
                    throw new \Exception('Cannot reduce capacity below currently occupied slots.');
                }

                $parkingLocation->four_wheeler_capacity = $request->four_wheeler_capacity;
                $parkingLocation->save();

                $fourWheelerAvailability->update([
                    'available_slots' => $request->four_wheeler_capacity - $currentlyOccupied,
                    'total_slots' => $request->four_wheeler_capacity,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Parking location updated successfully.',
                'parking_location' => new ParkingLocationResource($parkingLocation->load('slotAvailabilities')),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update parking location.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle the active status of the parking location.
     */
    public function toggleStatus(ParkingLocation $parkingLocation): JsonResponse
    {
        // Check if the parking location belongs to the authenticated owner
        if ($parkingLocation->owner_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        // Toggle is_active using a direct update query
        DB::table('parking_locations')
            ->where('id', $parkingLocation->id)
            ->update(['is_active' => DB::raw('NOT is_active')]); // PostgreSQL handles boolean correctly

        // Fetch the updated status
        $updatedStatus = DB::table('parking_locations')
            ->where('id', $parkingLocation->id)
            ->value('is_active');

        return response()->json([
            'message' => 'Parking location status updated successfully.',
            'is_active' => $updatedStatus,
        ]);
    }

    /**
     * Get parking locations owned by the authenticated user.
     */
    public function myParkingLocations(): JsonResponse
    {
        // Check if user is an owner
        if (!Auth::user()->isOwner()) {
            return response()->json([
                'message' => 'Only parking owners can access this endpoint.',
            ], 403);
        }

        $parkingLocations = Auth::user()->parkingLocations()->with('slotAvailabilities')->get();

        return response()->json([
            'parking_locations' => ParkingLocationResource::collection($parkingLocations),
        ]);
    }
}
