<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Http\Resources\ParkingLocationResource;
use App\Models\ParkingBooking;
use App\Models\ParkingLocation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OwnerController extends Controller
{
    /**
     * Constructor to ensure only owners can access these endpoints
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!Auth::user()->isOwner()) {
                return response()->json([
                    'message' => 'Unauthorized. Owner access required.',
                ], 403);
            }
            return $next($request);
        });
    }

    /**
     * Get dashboard statistics for owner
     */
    public function dashboard(): JsonResponse
    {
        $ownerId = Auth::id();

        // Get all parking locations owned by this owner
        $parkingLocations = ParkingLocation::where('owner_id', $ownerId)->get();
        $parkingLocationIds = $parkingLocations->pluck('id')->toArray();

        // Count statistics
        $totalParkingLocations = $parkingLocations->count();
        $activeParkingLocations = $parkingLocations->where('is_active', true)->count();

        $totalTwoWheelerCapacity = $parkingLocations->sum('two_wheeler_capacity');
        $totalFourWheelerCapacity = $parkingLocations->sum('four_wheeler_capacity');

        // Get bookings for these parking locations
        $totalBookings = ParkingBooking::whereIn('parking_location_id', $parkingLocationIds)->count();
        $activeBookings = ParkingBooking::whereIn('parking_location_id', $parkingLocationIds)
            ->whereIn('status', ['upcoming', 'checked_in'])
            ->count();
        $completedBookings = ParkingBooking::whereIn('parking_location_id', $parkingLocationIds)
            ->where('status', 'completed')
            ->count();

        // Revenue calculation
        $totalRevenue = ParkingBooking::whereIn('parking_location_id', $parkingLocationIds)
            ->whereIn('status', ['checked_in', 'completed'])
            ->sum('amount');

        // Today's revenue
        $todayRevenue = ParkingBooking::whereIn('parking_location_id', $parkingLocationIds)
            ->whereIn('status', ['checked_in', 'completed'])
            ->whereDate('created_at', Carbon::today())
            ->sum('amount');

        // Recent bookings
        $recentBookings = ParkingBooking::whereIn('parking_location_id', $parkingLocationIds)
            ->with(['user', 'vehicle', 'parkingLocation'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'statistics' => [
                'total_parking_locations' => $totalParkingLocations,
                'active_parking_locations' => $activeParkingLocations,
                'total_two_wheeler_capacity' => $totalTwoWheelerCapacity,
                'total_four_wheeler_capacity' => $totalFourWheelerCapacity,
                'total_bookings' => $totalBookings,
                'active_bookings' => $activeBookings,
                'completed_bookings' => $completedBookings,
                'total_revenue' => $totalRevenue,
                'today_revenue' => $todayRevenue,
            ],
            'recent_bookings' => BookingResource::collection($recentBookings),
        ]);
    }

    /**
     * Get all bookings for owner's parking locations
     */
    public function bookings(Request $request): JsonResponse
    {
        $ownerId = Auth::id();
        $parkingLocationIds = ParkingLocation::where('owner_id', $ownerId)->pluck('id')->toArray();

        $query = ParkingBooking::whereIn('parking_location_id', $parkingLocationIds)
            ->with(['user', 'vehicle', 'parkingLocation']);

        // Filter by status
        if ($request->has('status') && in_array($request->status, ['upcoming', 'checked_in', 'completed', 'cancelled'])) {
            $query->where('status', $request->status);
        }

        // Filter by booking type
        if ($request->has('booking_type') && in_array($request->booking_type, ['check_in', 'advance'])) {
            $query->where('booking_type', $request->booking_type);
        }

        // Filter by parking location
        if ($request->has('parking_location_id')) {
            $query->where('parking_location_id', $request->parking_location_id);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('check_in_time', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('check_in_time', '<=', $request->end_date);
        }

        $bookings = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'bookings' => BookingResource::collection($bookings),
            'pagination' => [
                'total' => $bookings->total(),
                'per_page' => $bookings->perPage(),
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
            ],
        ]);
    }

    /**
     * Get all parking locations owned by the authenticated owner
     */
    public function parkingLocations(Request $request): JsonResponse
    {
        $query = ParkingLocation::where('owner_id', Auth::id())
            ->with('slotAvailabilities');

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active === 'true');
        }

        // Search by name or address
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        $parkingLocations = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'parking_locations' => ParkingLocationResource::collection($parkingLocations),
        ]);
    }

    /**
     * Get details of a specific parking location
     */
    public function parkingLocationDetails(ParkingLocation $parkingLocation): JsonResponse
    {
        // Check if the parking location belongs to the authenticated owner
        if ($parkingLocation->owner_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized. This parking location does not belong to you.',
            ], 403);
        }

        $parkingLocation->load(['slotAvailabilities']);

        // Get today's bookings for this location
        $todayBookings = ParkingBooking::where('parking_location_id', $parkingLocation->id)
            ->whereIn('status', ['upcoming', 'checked_in'])
            ->whereDate('check_in_time', Carbon::today())
            ->with(['user', 'vehicle'])
            ->get();

        // Get revenue statistics
        $todayRevenue = ParkingBooking::where('parking_location_id', $parkingLocation->id)
            ->whereIn('status', ['checked_in', 'completed'])
            ->whereDate('created_at', Carbon::today())
            ->sum('amount');

        $weekRevenue = ParkingBooking::where('parking_location_id', $parkingLocation->id)
            ->whereIn('status', ['checked_in', 'completed'])
            ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->sum('amount');

        $monthRevenue = ParkingBooking::where('parking_location_id', $parkingLocation->id)
            ->whereIn('status', ['checked_in', 'completed'])
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('amount');

        return response()->json([
            'parking_location' => new ParkingLocationResource($parkingLocation),
            'today_bookings' => BookingResource::collection($todayBookings),
            'revenue' => [
                'today' => $todayRevenue,
                'this_week' => $weekRevenue,
                'this_month' => $monthRevenue,
            ],
        ]);
    }

    /**
     * Get booking details for a specific booking
     */
    public function bookingDetails(ParkingBooking $booking): JsonResponse
    {
        // Check if the booking belongs to one of the owner's parking locations
        $ownerId = Auth::id();
        $parkingLocationIds = ParkingLocation::where('owner_id', $ownerId)->pluck('id')->toArray();

        if (!in_array($booking->parking_location_id, $parkingLocationIds)) {
            return response()->json([
                'message' => 'Unauthorized. This booking does not belong to your parking locations.',
            ], 403);
        }

        $booking->load(['user', 'vehicle', 'parkingLocation']);

        return response()->json([
            'booking' => new BookingResource($booking),
        ]);
    }

    /**
     * Update check-in status for a booking (when user arrives at parking)
     */
    public function checkInBooking(Request $request, ParkingBooking $booking): JsonResponse
    {
        // Validate request
        $request->validate([
            'qr_code' => 'required|string',
        ]);

        // Check if the booking belongs to one of the owner's parking locations
        $ownerId = Auth::id();
        $parkingLocationIds = ParkingLocation::where('owner_id', $ownerId)->pluck('id')->toArray();

        if (!in_array($booking->parking_location_id, $parkingLocationIds)) {
            return response()->json([
                'message' => 'Unauthorized. This booking does not belong to your parking locations.',
            ], 403);
        }

        // Verify QR code
        if ($booking->qr_code !== $request->qr_code) {
            return response()->json([
                'message' => 'Invalid QR code.',
            ], 422);
        }

        // Check if booking is in valid state for check-in
        if ($booking->status !== 'upcoming') {
            return response()->json([
                'message' => 'This booking cannot be checked in. Current status: ' . $booking->status,
            ], 422);
        }

        // Update booking status
        $booking->status = 'checked_in';
        $booking->save();

        return response()->json([
            'message' => 'Booking checked in successfully.',
            'booking' => new BookingResource($booking->load(['user', 'vehicle', 'parkingLocation'])),
        ]);
    }

    /**
     * Update check-out status for a booking (when user leaves parking)
     */
    public function checkOutBooking(ParkingBooking $booking): JsonResponse
    {
        // Check if the booking belongs to one of the owner's parking locations
        $ownerId = Auth::id();
        $parkingLocationIds = ParkingLocation::where('owner_id', $ownerId)->pluck('id')->toArray();

        if (!in_array($booking->parking_location_id, $parkingLocationIds)) {
            return response()->json([
                'message' => 'Unauthorized. This booking does not belong to your parking locations.',
            ], 403);
        }

        // Check if booking is in valid state for check-out
        if ($booking->status !== 'checked_in') {
            return response()->json([
                'message' => 'This booking cannot be checked out. Current status: ' . $booking->status,
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
                'message' => 'Booking checked out successfully.',
                'booking' => new BookingResource($booking->load(['user', 'vehicle', 'parkingLocation'])),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to check out booking.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get revenue reports for owner's parking locations
     */
    public function revenueReports(Request $request): JsonResponse
    {
        $ownerId = Auth::id();
        $parkingLocationIds = ParkingLocation::where('owner_id', $ownerId)->pluck('id')->toArray();

        // Validate request
        $request->validate([
            'period' => 'required|in:daily,weekly,monthly,yearly',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'parking_location_id' => 'nullable|exists:parking_locations,id',
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        // Limit to specific parking location if provided
        if ($request->has('parking_location_id')) {
            if (!in_array($request->parking_location_id, $parkingLocationIds)) {
                return response()->json([
                    'message' => 'Unauthorized. This parking location does not belong to you.',
                ], 403);
            }

            $parkingLocationIds = [$request->parking_location_id];
        }

        $query = ParkingBooking::whereIn('parking_location_id', $parkingLocationIds)
            ->whereIn('status', ['checked_in', 'completed'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        $reportData = [];

        switch ($request->period) {
            case 'daily':
                $reportData = $this->getDailyRevenueReport($query, $startDate, $endDate);
                break;
            case 'weekly':
                $reportData = $this->getWeeklyRevenueReport($query, $startDate, $endDate);
                break;
            case 'monthly':
                $reportData = $this->getMonthlyRevenueReport($query, $startDate, $endDate);
                break;
            case 'yearly':
                $reportData = $this->getYearlyRevenueReport($query, $startDate, $endDate);
                break;
        }

        return response()->json([
            'period' => $request->period,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'report_data' => $reportData,
        ]);
    }

    /**
     * Get daily revenue report
     */
    private function getDailyRevenueReport($query, $startDate, $endDate)
    {
        $result = [];
        $currentDate = clone $startDate;

        while ($currentDate <= $endDate) {
            $dateString = $currentDate->toDateString();

            $dailyRevenue = (clone $query)
                ->whereDate('created_at', $dateString)
                ->sum('amount');

            $dailyBookings = (clone $query)
                ->whereDate('created_at', $dateString)
                ->count();

            $result[] = [
                'date' => $dateString,
                'revenue' => $dailyRevenue,
                'bookings' => $dailyBookings,
            ];

            $currentDate->addDay();
        }

        return $result;
    }

    /**
     * Get weekly revenue report
     */
    private function getWeeklyRevenueReport($query, $startDate, $endDate)
    {
        $result = [];
        $currentDate = clone $startDate->startOfWeek();

        while ($currentDate <= $endDate) {
            $weekStart = clone $currentDate;
            $weekEnd = clone $currentDate->endOfWeek();

            if ($weekEnd > $endDate) {
                $weekEnd = clone $endDate;
            }

            $weeklyRevenue = (clone $query)
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->sum('amount');

            $weeklyBookings = (clone $query)
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->count();

            $result[] = [
                'week' => 'Week ' . $weekStart->weekOfYear . ' (' . $weekStart->toDateString() . ' to ' . $weekEnd->toDateString() . ')',
                'revenue' => $weeklyRevenue,
                'bookings' => $weeklyBookings,
            ];

            $currentDate->addWeek();
        }

        return $result;
    }

    /**
     * Get monthly revenue report
     */
    private function getMonthlyRevenueReport($query, $startDate, $endDate)
    {
        $result = [];
        $currentDate = clone $startDate->startOfMonth();

        while ($currentDate <= $endDate) {
            $monthStart = clone $currentDate;
            $monthEnd = clone $currentDate->endOfMonth();

            if ($monthEnd > $endDate) {
                $monthEnd = clone $endDate;
            }

            $monthlyRevenue = (clone $query)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('amount');

            $monthlyBookings = (clone $query)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count();

            $result[] = [
                'month' => $monthStart->format('F Y'),
                'revenue' => $monthlyRevenue,
                'bookings' => $monthlyBookings,
            ];

            $currentDate->addMonth();
        }

        return $result;
    }

    /**
     * Get yearly revenue report
     */
    private function getYearlyRevenueReport($query, $startDate, $endDate)
    {
        $result = [];
        $currentDate = clone $startDate->startOfYear();

        while ($currentDate <= $endDate) {
            $yearStart = clone $currentDate;
            $yearEnd = clone $currentDate->endOfYear();

            if ($yearEnd > $endDate) {
                $yearEnd = clone $endDate;
            }

            $yearlyRevenue = (clone $query)
                ->whereBetween('created_at', [$yearStart, $yearEnd])
                ->sum('amount');

            $yearlyBookings = (clone $query)
                ->whereBetween('created_at', [$yearStart, $yearEnd])
                ->count();

            $result[] = [
                'year' => $yearStart->year,
                'revenue' => $yearlyRevenue,
                'bookings' => $yearlyBookings,
            ];

            $currentDate->addYear();
        }

        return $result;
    }
}
