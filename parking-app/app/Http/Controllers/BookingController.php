<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdvanceBookingRequest;
use App\Http\Requests\StoreCheckInBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\ParkingBooking;
use App\Models\ParkingLocation;
use App\Models\ParkingTimeSlot;
use App\Models\Vehicle;
use App\Services\QrCodeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    protected $qrCodeService;

    public function __construct(QrCodeService $qrCodeService)
    {
        $this->qrCodeService = $qrCodeService;
    }

    /**
     * Display a listing of the user's bookings.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Auth::user()->bookings()->with(['vehicle', 'parkingLocation']);

        // Filter by status
        if ($request->has('status') && in_array($request->status, ['upcoming', 'checked_in', 'completed', 'cancelled'])) {
            $query->where('status', $request->status);
        }

        $bookings = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'bookings' => BookingResource::collection($bookings),
        ]);
    }

    /**
     * Store a new check-in booking.
     */
    public function storeCheckIn(StoreCheckInBookingRequest $request): JsonResponse
    {
        // Validate vehicle belongs to user
        $vehicle = Vehicle::find($request->vehicle_id);
        if ($vehicle->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'The vehicle does not belong to you.',
            ], 403);
        }

        // Get parking location
        $parkingLocation = ParkingLocation::findOrFail($request->parking_location_id);
        if (!$parkingLocation->is_active) {
            return response()->json([
                'message' => 'This parking location is not active.',
            ], 422);
        }

        // Determine vehicle type and check availability
        $vehicleType = $vehicle->type === '2-wheeler' ? '2-wheeler' : '4-wheeler';
        $slotAvailability = $parkingLocation->slotAvailabilities()
            ->where('vehicle_type', $vehicleType)
            ->first();

        if (!$slotAvailability || $slotAvailability->available_slots <= 0) {
            return response()->json([
                'message' => 'No parking slots available for your vehicle type.',
            ], 422);
        }

        // Calculate amount based on vehicle type and duration
        $hourlyRate = $vehicleType === '2-wheeler'
            ? $parkingLocation->two_wheeler_hourly_rate
            : $parkingLocation->four_wheeler_hourly_rate;
        $amount = $hourlyRate * $request->duration_hours;

        DB::beginTransaction();

        try {
            // Create booking
            $booking = ParkingBooking::create([
                'user_id' => Auth::id(),
                'vehicle_id' => $request->vehicle_id,
                'parking_location_id' => $request->parking_location_id,
                'booking_type' => 'check_in',
                'status' => 'checked_in',
                'check_in_time' => now(),
                'check_out_time' => now()->addHours($request->duration_hours),
                'duration_hours' => $request->duration_hours,
                'amount' => $amount,
                'qr_code' => $this->qrCodeService->generateQrCode(),
            ]);

            // Update slot availability
            $slotAvailability->available_slots -= 1;
            $slotAvailability->save();

            DB::commit();

            return response()->json([
                'message' => 'Parking booked successfully.',
                'booking' => new BookingResource($booking->load(['vehicle', 'parkingLocation'])),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to book parking.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new advance booking.
     */
    public function storeAdvance(StoreAdvanceBookingRequest $request): JsonResponse
    {
        // Validate vehicle belongs to user
        $vehicle = Vehicle::find($request->vehicle_id);
        if ($vehicle->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'The vehicle does not belong to you.',
            ], 403);
        }

        // Get parking location
        $parkingLocation = ParkingLocation::findOrFail($request->parking_location_id);
        if (!$parkingLocation->is_active) {
            return response()->json([
                'message' => 'This parking location is not active.',
            ], 422);
        }

        // Parse date and times
        $date = Carbon::parse($request->date)->toDateString();
        $startTime = Carbon::parse($request->start_time);
        $endTime = Carbon::parse($request->end_time);
        $durationHours = $startTime->diffInHours($endTime);

        if ($durationHours < 1) {
            return response()->json([
                'message' => 'Booking duration must be at least 1 hour.',
            ], 422);
        }

        // Check if the requested time is in the future
        $checkInDateTime = Carbon::parse($date . ' ' . $request->start_time);
        if ($checkInDateTime->isPast()) {
            return response()->json([
                'message' => 'Cannot book for past dates or times.',
            ], 422);
        }

        // Determine vehicle type
        $vehicleType = $vehicle->type === '2-wheeler' ? '2-wheeler' : '4-wheeler';

        // Check if time slot is available
        $timeSlot = ParkingTimeSlot::firstOrCreate(
            [
                'parking_location_id' => $parkingLocation->id,
                'vehicle_type' => $vehicleType,
                'date' => $date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
            ],
            [
                'available_slots' => $vehicleType === '2-wheeler'
                    ? $parkingLocation->two_wheeler_capacity
                    : $parkingLocation->four_wheeler_capacity,
            ]
        );

        if ($timeSlot->available_slots <= 0) {
            return response()->json([
                'message' => 'No parking slots available for the selected time slot.',
            ], 422);
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
                'user_id' => Auth::id(),
                'vehicle_id' => $request->vehicle_id,
                'parking_location_id' => $request->parking_location_id,
                'booking_type' => 'advance',
                'status' => 'upcoming',
                'check_in_time' => $checkInDateTime,
                'check_out_time' => Carbon::parse($date . ' ' . $request->end_time),
                'duration_hours' => $durationHours,
                'amount' => $amount,
                'qr_code' => $this->qrCodeService->generateQrCode(),
            ]);

            // Update time slot availability
            $timeSlot->available_slots -= 1;
            $timeSlot->save();

            DB::commit();

            return response()->json([
                'message' => 'Advance parking booked successfully.',
                'booking' => new BookingResource($booking->load(['vehicle', 'parkingLocation'])),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to book parking.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified booking.
     */
    public function show(ParkingBooking $booking): JsonResponse
    {
        // Check if the booking belongs to the authenticated user
        if ($booking->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        return response()->json([
            'booking' => new BookingResource($booking->load(['vehicle', 'parkingLocation'])),
        ]);
    }

    /**
     * Cancel the specified booking.
     */
    public function cancel(ParkingBooking $booking): JsonResponse
    {
        // Check if the booking belongs to the authenticated user
        if ($booking->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        // Check if the booking can be cancelled
        if ($booking->status !== 'upcoming') {
            return response()->json([
                'message' => 'Only upcoming bookings can be cancelled.',
            ], 422);
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

            return response()->json([
                'message' => 'Booking cancelled successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to cancel booking.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Complete the specified booking (check-out).
     */
    public function complete(ParkingBooking $booking): JsonResponse
    {
        // This would typically be triggered by the system or an admin/owner
        // For now, we'll allow the user to complete their own booking

        // Check if the booking belongs to the authenticated user
        if ($booking->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        // Check if the booking can be completed
        if ($booking->status !== 'checked_in') {
            return response()->json([
                'message' => 'Only checked-in bookings can be completed.',
            ], 422);
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

            return response()->json([
                'message' => 'Booking completed successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to complete booking.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify QR code for check-in.
     */
    public function verifyQrCode(Request $request): JsonResponse
    {
        $request->validate([
            'qr_code' => 'required|string',
        ]);

        $booking = ParkingBooking::where('qr_code', $request->qr_code)
            ->with(['vehicle', 'parkingLocation', 'user'])
            ->first();

        if (!$booking) {
            return response()->json([
                'message' => 'Invalid QR code.',
                'is_valid' => false,
            ], 422);
        }

        // For advance bookings, update status to checked-in when QR is scanned
        if ($booking->booking_type === 'advance' && $booking->status === 'upcoming') {
            // Check if the booking time is valid (not too early or too late)
            $now = now();
            $checkInTime = $booking->check_in_time;

            // Allow check-in 15 minutes before scheduled time
            if ($now->lt($checkInTime->subMinutes(15))) {
                return response()->json([
                    'message' => 'You are too early for check-in. Please come back closer to your scheduled time.',
                    'is_valid' => false,
                    'booking' => new BookingResource($booking),
                ], 422);
            }

            // Update booking status
            $booking->status = 'checked_in';
            $booking->save();

            // No need to update slot availability as it was already accounted for during advance booking
        }

        return response()->json([
            'message' => 'QR code verified successfully.',
            'is_valid' => true,
            'booking' => new BookingResource($booking),
        ]);
    }

    /**
     * Get available time slots for a specific date and parking location.
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
}
