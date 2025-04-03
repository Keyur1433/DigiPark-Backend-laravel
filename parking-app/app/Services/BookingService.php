<?php

namespace App\Services;

use App\Models\ParkingBooking;
use App\Models\ParkingLocation;
use App\Models\ParkingSlotAvailability;
use App\Models\ParkingTimeSlot;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BookingService
{
    protected $qrCodeService;

    public function __construct(QrCodeService $qrCodeService)
    {
        $this->qrCodeService = $qrCodeService;
    }

    /**
     * Create a check-in booking
     */
    public function createCheckInBooking(int $userId, int $vehicleId, int $parkingLocationId, int $durationHours): ParkingBooking
    {
        // Get vehicle and parking location
        $vehicle = Vehicle::findOrFail($vehicleId);
        $parkingLocation = ParkingLocation::findOrFail($parkingLocationId);

        // Validate vehicle belongs to user
        if ($vehicle->user_id !== $userId) {
            throw new \Exception('The vehicle does not belong to you.');
        }

        // Check if parking location is active
        if (!$parkingLocation->is_active) {
            throw new \Exception('This parking location is not active.');
        }

        // Determine vehicle type and check availability
        $vehicleType = $vehicle->type === '2-wheeler' ? '2-wheeler' : '4-wheeler';
        $slotAvailability = $parkingLocation->slotAvailabilities()
            ->where('vehicle_type', $vehicleType)
            ->first();

        if (!$slotAvailability || $slotAvailability->available_slots <= 0) {
            throw new \Exception('No parking slots available for your vehicle type.');
        }

        // Calculate amount based on vehicle type and duration
        $hourlyRate = $vehicleType === '2-wheeler'
            ? $parkingLocation->two_wheeler_hourly_rate
            : $parkingLocation->four_wheeler_hourly_rate;
        $amount = $hourlyRate * $durationHours;

        DB::beginTransaction();

        try {
            // Create booking
            $booking = ParkingBooking::create([
                'user_id' => $userId,
                'vehicle_id' => $vehicleId,
                'parking_location_id' => $parkingLocationId,
                'booking_type' => 'check_in',
                'status' => 'checked_in',
                'check_in_time' => now(),
                'check_out_time' => now()->addHours($durationHours),
                'duration_hours' => $durationHours,
                'amount' => $amount,
                'qr_code' => $this->qrCodeService->generateQrCode(),
            ]);

            // Update slot availability
            $slotAvailability->available_slots -= 1;
            $slotAvailability->save();

            DB::commit();

            return $booking;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Create an advance booking
     */
    public function createAdvanceBooking(int $userId, int $vehicleId, int $parkingLocationId, string $date, string $startTime, string $endTime): ParkingBooking
    {
        // Get vehicle and parking location
        $vehicle = Vehicle::findOrFail($vehicleId);
        $parkingLocation = ParkingLocation::findOrFail($parkingLocationId);

        // Validate vehicle belongs to user
        if ($vehicle->user_id !== $userId) {
            throw new \Exception('The vehicle does not belong to you.');
        }

        // Check if parking location is active
        if (!$parkingLocation->is_active) {
            throw new \Exception('This parking location is not active.');
        }

        // Parse date and times
        $parsedDate = Carbon::parse($date)->toDateString();
        $parsedStartTime = Carbon::parse($startTime);
        $parsedEndTime = Carbon::parse($endTime);
        $durationHours = $parsedStartTime->diffInHours($parsedEndTime);

        if ($durationHours < 1) {
            throw new \Exception('Booking duration must be at least 1 hour.');
        }

        // Check if the requested time is in the future
        $checkInDateTime = Carbon::parse($parsedDate . ' ' . $startTime);
        if ($checkInDateTime->isPast()) {
            throw new \Exception('Cannot book for past dates or times.');
        }

        // Determine vehicle type
        $vehicleType = $vehicle->type === '2-wheeler' ? '2-wheeler' : '4-wheeler';

        // Check if time slot is available
        $timeSlot = ParkingTimeSlot::firstOrCreate(
            [
                'parking_location_id' => $parkingLocation->id,
                'vehicle_type' => $vehicleType,
                'date' => $parsedDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
            ],
            [
                'available_slots' => $vehicleType === '2-wheeler'
                    ? $parkingLocation->two_wheeler_capacity
                    : $parkingLocation->four_wheeler_capacity,
            ]
        );

        if ($timeSlot->available_slots <= 0) {
            throw new \Exception('No parking slots available for the selected time slot.');
        }

        // Calculate amount based on vehicle type and duration
        $hourlyRate = $vehicleType === '2-wheeler'
            ? $parkingLocation->two_wheeler_hourly_rate
            : $parkingLocation->four_wheeler_hourly_rate;
        $amount = $hourlyRate * $durationHours;

        DB::beginTransaction();

        try {
            // Create booking
            $booking = ParkingBooking::create([
                'user_id' => $userId,
                'vehicle_id' => $vehicleId,
                'parking_location_id' => $parkingLocation->id,
                'booking_type' => 'advance',
                'status' => 'upcoming',
                'check_in_time' => $checkInDateTime,
                'check_out_time' => Carbon::parse($parsedDate . ' ' . $endTime),
                'duration_hours' => $durationHours,
                'amount' => $amount,
                'qr_code' => $this->qrCodeService->generateQrCode(),
            ]);

            // Update time slot availability
            $timeSlot->available_slots -= 1;
            $timeSlot->save();

            DB::commit();

            return $booking;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Cancel a booking
     */
    public function cancelBooking(ParkingBooking $booking): bool
    {
        // Check if the booking can be cancelled
        if ($booking->status !== 'upcoming') {
            throw new \Exception('Only upcoming bookings can be cancelled.');
        }

        DB::beginTransaction();

        try {
            // Update booking status
            $booking->status = 'cancelled';
            $booking->save();

            // If it's an advance booking, update time slot availability
            if ($booking->booking_type === 'advance') {
                $vehicleType = $booking->vehicle->type === '2-wheeler' ? '2-wheeler' : '4-wheeler';
                $date = $booking->check_in_time->toDateString();
                $startTime = $booking->check_in_time->format('H:i:s');
                $endTime = $booking->check_out_time->format('H:i:s');

                $timeSlot = ParkingTimeSlot::where([
                    'parking_location_id' => $booking->parking_location_id,
                    'vehicle_type' => $vehicleType,
                    'date' => $date,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                ])->first();

                if ($timeSlot) {
                    $timeSlot->available_slots += 1;
                    $timeSlot->save();
                }
            }

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Complete a booking (check-out)
     */
    public function completeBooking(ParkingBooking $booking): bool
    {
        // Check if the booking can be completed
        if ($booking->status !== 'checked_in') {
            throw new \Exception('Only checked-in bookings can be completed.');
        }

        DB::beginTransaction();

        try {
            // Update booking status
            $booking->status = 'completed';
            $booking->save();

            // Update slot availability
            $vehicleType = $booking->vehicle->type === '2-wheeler' ? '2-wheeler' : '4-wheeler';
            $slotAvailability = $booking->parkingLocation->slotAvailabilities()
                ->where('vehicle_type', $vehicleType)
                ->first();

            if ($slotAvailability) {
                $slotAvailability->available_slots += 1;
                $slotAvailability->save();
            }

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Process check-in for advance booking
     */
    public function processAdvanceCheckIn(ParkingBooking $booking): bool
    {
        // Check if the booking is an advance booking and is upcoming
        if ($booking->booking_type !== 'advance' || $booking->status !== 'upcoming') {
            throw new \Exception('Only upcoming advance bookings can be checked in.');
        }

        // Check if the booking time is valid (not too early or too late)
        $now = now();
        $checkInTime = $booking->check_in_time;

        // Allow check-in 15 minutes before scheduled time
        if ($now->lt($checkInTime->subMinutes(15))) {
            throw new \Exception('You are too early for check-in. Please come back closer to your scheduled time.');
        }

        // Update booking status
        $booking->status = 'checked_in';
        $booking->save();

        return true;
    }
}
