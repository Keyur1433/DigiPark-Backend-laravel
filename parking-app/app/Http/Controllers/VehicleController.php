<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVehicleRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class VehicleController extends Controller
{
    /**
     * Display a listing of the user's vehicles.
     */
    public function index(): JsonResponse
    {
        $vehicles = Auth::user()->vehicles;

        return response()->json([
            'vehicles' => VehicleResource::collection($vehicles),
        ]);
    }

    /**
     * Store a newly created vehicle in storage.
     */
    public function store(StoreVehicleRequest $request): JsonResponse
    {
        $vehicle = Vehicle::create([
            'user_id' => Auth::id(),
            'type' => $request->type,
            'number_plate' => $request->number_plate,
            'brand' => $request->brand,
            'model' => $request->model,
            'color' => $request->color,
        ]);

        return response()->json([
            'message' => 'Vehicle added successfully.',
            'vehicle' => new VehicleResource($vehicle),
        ], 201);
    }

    /**
     * Display the specified vehicle.
     */
    public function show(Vehicle $vehicle): JsonResponse
    {
        // Check if the vehicle belongs to the authenticated user
        if ($vehicle->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        return response()->json([
            'vehicle' => new VehicleResource($vehicle),
        ]);
    }

    /**
     * Update the specified vehicle in storage.
     */
    public function update(StoreVehicleRequest $request, Vehicle $vehicle): JsonResponse
    {
        // Check if the vehicle belongs to the authenticated user
        if ($vehicle->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $vehicle->update([
            'type' => $request->type,
            'number_plate' => $request->number_plate,
            'brand' => $request->brand,
            'model' => $request->model,
            'color' => $request->color,
        ]);

        return response()->json([
            'message' => 'Vehicle updated successfully.',
            'vehicle' => new VehicleResource($vehicle),
        ]);
    }

    /**
     * Remove the specified vehicle from storage.
     */
    public function destroy(Vehicle $vehicle): JsonResponse
    {
        // Check if the vehicle belongs to the authenticated user
        if ($vehicle->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        // Check if the vehicle is currently being used in an active booking
        $activeBooking = $vehicle->bookings()
            ->whereIn('status', ['upcoming', 'checked_in'])
            ->first();

        if ($activeBooking) {
            return response()->json([
                'message' => 'Cannot delete vehicle with active bookings.',
            ], 422);
        }

        $vehicle->delete();

        return response()->json([
            'message' => 'Vehicle deleted successfully.',
        ]);
    }
}
