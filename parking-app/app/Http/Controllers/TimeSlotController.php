<?php

namespace App\Http\Controllers;

use App\Models\ParkingLocation;
use App\Models\ParkingTimeSlot;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimeSlotController extends Controller
{
    /**
     * Get available time slots for a specific date and parking location
     */
    public function getAvailableTimeSlots(Request $request): JsonResponse
    {
        $request->validate([
            'parking_location_id' => 'required|exists:parking_locations,id',
            'date' => 'required|date|after_or_equal:today',
            'vehicle_type' => 'required|in:2-wheeler,4-wheeler',
        ]);

        $parkingLocation = ParkingLocation::findOrFail($request->parking_location_id);
        $date = Carbon::parse($request->date)->toDateString();
        $vehicleType = $request->vehicle_type;

        // Check if parking location is active
        if (!$parkingLocation->is_active) {
            return response()->json([
                'message' => 'This parking location is not active.',
            ], 422);
        }

        // Get all existing time slots for this date, location and vehicle type
        $existingTimeSlots = ParkingTimeSlot::where([
            'parking_location_id' => $parkingLocation->id,
            'vehicle_type' => $vehicleType,
            'date' => $date,
        ])->get();

        // Generate all possible time slots (30-minute intervals)
        $allTimeSlots = [];
        $startOfDay = Carbon::parse($date . ' 00:00:00');
        $endOfDay = Carbon::parse($date . ' 23:30:00');

        while ($startOfDay <= $endOfDay) {
            $endTime = (clone $startOfDay)->addMinutes(30);

            // Skip time slots in the past for today
            if ($date === Carbon::today()->toDateString() && $startOfDay < Carbon::now()) {
                $startOfDay->addMinutes(30);
                continue;
            }

            $startTimeStr = $startOfDay->format('H:i');
            $endTimeStr = $endTime->format('H:i');

            // Check if this time slot exists in the database
            $existingSlot = $existingTimeSlots->first(function ($slot) use ($startTimeStr, $endTimeStr) {
                return $slot->start_time->format('H:i') === $startTimeStr &&
                    $slot->end_time->format('H:i') === $endTimeStr;
            });

            $capacity = $vehicleType === '2-wheeler'
                ? $parkingLocation->two_wheeler_capacity
                : $parkingLocation->four_wheeler_capacity;

            $availableSlots = $existingSlot ? $existingSlot->available_slots : $capacity;

            $allTimeSlots[] = [
                'start_time' => $startTimeStr,
                'end_time' => $endTimeStr,
                'available_slots' => $availableSlots,
                'is_available' => $availableSlots > 0,
            ];

            $startOfDay->addMinutes(30);
        }

        return response()->json([
            'date' => $date,
            'vehicle_type' => $vehicleType,
            'time_slots' => $allTimeSlots,
        ]);
    }

    /**
     * Get available dates for a specific parking location
     */
    public function getAvailableDates(Request $request): JsonResponse
    {
        $request->validate([
            'parking_location_id' => 'required|exists:parking_locations,id',
            'vehicle_type' => 'required|in:2-wheeler,4-wheeler',
        ]);

        $parkingLocation = ParkingLocation::findOrFail($request->parking_location_id);
        $vehicleType = $request->vehicle_type;

        // Check if parking location is active
        if (!$parkingLocation->is_active) {
            return response()->json([
                'message' => 'This parking location is not active.',
            ], 422);
        }

        // Generate dates for the next 30 days
        $dates = [];
        $today = Carbon::today();

        for ($i = 0; $i < 30; $i++) {
            $date = (clone $today)->addDays($i);
            $dateString = $date->toDateString();

            // Check if there are any fully booked time slots for this date
            $fullyBookedSlots = ParkingTimeSlot::where([
                'parking_location_id' => $parkingLocation->id,
                'vehicle_type' => $vehicleType,
                'date' => $dateString,
                'available_slots' => 0,
            ])->count();

            // Get total capacity
            $capacity = $vehicleType === '2-wheeler'
                ? $parkingLocation->two_wheeler_capacity
                : $parkingLocation->four_wheeler_capacity;

            // If capacity is 0, the date is not available
            $isAvailable = $capacity > 0;

            // If there are any fully booked slots, mark as partially available
            $status = 'available';
            if (!$isAvailable) {
                $status = 'unavailable';
            } elseif ($fullyBookedSlots > 0) {
                $status = 'partial';
            }

            $dates[] = [
                'date' => $dateString,
                'day' => $date->format('D'),
                'day_number' => $date->day,
                'month' => $date->format('M'),
                'status' => $status,
            ];
        }

        return response()->json([
            'vehicle_type' => $vehicleType,
            'dates' => $dates,
        ]);
    }
}
