<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Http\Requests\ChangePasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Get authenticated user profile
     */
    public function profile(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'state' => 'sometimes|required|string|max:255',
            'city' => 'sometimes|required|string|max:255',
            'country' => 'sometimes|required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update individual fields if they exist in the request
        if ($request->has('first_name')) {
            $user->first_name = $request->first_name;
        }
        
        if ($request->has('last_name')) {
            $user->last_name = $request->last_name;
        }
        
        if ($request->has('email')) {
            $user->email = $request->email;
        }
        
        if ($request->has('state')) {
            $user->state = $request->state;
        }
        
        if ($request->has('city')) {
            $user->city = $request->city;
        }
        
        if ($request->has('country')) {
            $user->country = $request->country;
        }
        
        // Save the changes
        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Change user password
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        // Request is already validated by ChangePasswordRequest
        $user = Auth::user();

        // Check if current password is correct
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        try {
            // Update password
            $user->password = Hash::make($request->password);
            $user->save();

            return response()->json([
                'message' => 'Password changed successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update password.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete user account
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        // Check if password is correct
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Password is incorrect.',
            ], 422);
        }

        // Check if user has active bookings
        $activeBookings = $user->bookings()
            ->whereIn('status', ['upcoming', 'checked_in'])
            ->exists();

        if ($activeBookings) {
            return response()->json([
                'message' => 'Cannot delete account with active bookings. Please cancel all active bookings first.',
            ], 422);
        }

        // For owners, check if they have parking locations with active bookings
        if ($user->isOwner()) {
            $parkingLocationIds = $user->parkingLocations()->pluck('id')->toArray();

            $activeLocationBookings = \App\Models\ParkingBooking::whereIn('parking_location_id', $parkingLocationIds)
                ->whereIn('status', ['upcoming', 'checked_in'])
                ->exists();

            if ($activeLocationBookings) {
                return response()->json([
                    'message' => 'Cannot delete account with active bookings in your parking locations.',
                ], 422);
            }
        }

        // Delete user's tokens
        $user->tokens()->delete();

        // Delete user
        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully.',
        ]);
    }
}
