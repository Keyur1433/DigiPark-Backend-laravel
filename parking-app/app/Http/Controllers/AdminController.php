<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Http\Resources\ParkingLocationResource;
use App\Http\Resources\UserResource;
use App\Models\ParkingBooking;
use App\Models\ParkingLocation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    /**
     * Constructor to ensure only admins can access these endpoints
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!Auth::user()->isAdmin()) {
                return response()->json([
                    'message' => 'Unauthorized. Admin access required.',
                ], 403);
            }
            return $next($request);
        });
    }

    /**
     * Get dashboard statistics for admin
     */
    public function dashboard(): JsonResponse
    {
        $totalUsers = User::where('role', 'user')->count();
        $totalOwners = User::where('role', 'owner')->count();
        $totalParkingLocations = ParkingLocation::count();
        $totalBookings = ParkingBooking::count();
        $activeBookings = ParkingBooking::whereIn('status', ['upcoming', 'checked_in'])->count();
        $completedBookings = ParkingBooking::where('status', 'completed')->count();
        $cancelledBookings = ParkingBooking::where('status', 'cancelled')->count();

        // Revenue calculation
        $totalRevenue = ParkingBooking::whereIn('status', ['checked_in', 'completed'])->sum('amount');

        // Recent bookings
        $recentBookings = ParkingBooking::with(['user', 'vehicle', 'parkingLocation'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'statistics' => [
                'total_users' => $totalUsers,
                'total_owners' => $totalOwners,
                'total_parking_locations' => $totalParkingLocations,
                'total_bookings' => $totalBookings,
                'active_bookings' => $activeBookings,
                'completed_bookings' => $completedBookings,
                'cancelled_bookings' => $cancelledBookings,
                'total_revenue' => $totalRevenue,
            ],
            'recent_bookings' => BookingResource::collection($recentBookings),
        ]);
    }

    /**
     * Get all users (with optional filtering)
     */
    public function users(Request $request): JsonResponse
    {
        $query = User::query();

        // Filter by role
        if ($request->has('role') && in_array($request->role, ['user', 'owner', 'admin'])) {
            $query->where('role', $request->role);
        }

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('contact_number', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'users' => UserResource::collection($users),
            'pagination' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    /**
     * Get user details with their vehicles and bookings
     */
    public function userDetails(User $user): JsonResponse
    {
        $user->load(['vehicles', 'bookings.parkingLocation']);

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Get all parking locations (with optional filtering)
     */
    public function parkingLocations(Request $request): JsonResponse
    {
        $query = ParkingLocation::with(['owner', 'slotAvailabilities']);

        // Filter by owner
        if ($request->has('owner_id')) {
            $query->where('owner_id', $request->owner_id);
        }

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

        $parkingLocations = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json([
            'parking_locations' => ParkingLocationResource::collection($parkingLocations),
            'pagination' => [
                'total' => $parkingLocations->total(),
                'per_page' => $parkingLocations->perPage(),
                'current_page' => $parkingLocations->currentPage(),
                'last_page' => $parkingLocations->lastPage(),
            ],
        ]);
    }

    /**
     * Get all bookings (with optional filtering)
     */
    public function bookings(Request $request): JsonResponse
    {
        $query = ParkingBooking::with(['user', 'vehicle', 'parkingLocation']);

        // Filter by status
        if ($request->has('status') && in_array($request->status, ['upcoming', 'checked_in', 'completed', 'cancelled'])) {
            $query->where('status', $request->status);
        }

        // Filter by booking type
        if ($request->has('booking_type') && in_array($request->booking_type, ['check_in', 'advance'])) {
            $query->where('booking_type', $request->booking_type);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
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
     * Toggle user account status (activate/deactivate)
     */
    public function toggleUserStatus(User $user): JsonResponse
    {
        // Don't allow admins to deactivate themselves
        if ($user->id === Auth::id()) {
            return response()->json([
                'message' => 'You cannot deactivate your own account.',
            ], 422);
        }

        DB::table('users')
            ->where('id', $user->id)
            ->update(['is_verified' => DB::raw('NOT is_verified')]);

        return response()->json([
            'message' => $user->is_verified
                ? 'User account activated successfully.'
                : 'User account deactivated successfully.',
            'is_verified' => $user->is_verified,
        ]);
    }

    /**
     * Toggle parking location status (activate/deactivate)
     */
    public function toggleParkingLocationStatus(ParkingLocation $parkingLocation): JsonResponse
    {
        DB::table('parking_locations')
            ->where('id', $parkingLocation->id)
            ->update(['is_active' => DB::raw('NOT is_active')]);

        return response()->json([
            'message' => $parkingLocation->is_active
                ? 'Parking location activated successfully.'
                : 'Parking location deactivated successfully.',
            'is_active' => $parkingLocation->is_active,
        ]);
    }
}
