<?php

namespace App\Services;

use App\Models\ParkingBooking;
use Carbon\Carbon;

class ReportService
{
    /**
     * Generate daily revenue report
     */
    public function getDailyRevenueReport($query, Carbon $startDate, Carbon $endDate): array
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
     * Generate weekly revenue report
     */
    public function getWeeklyRevenueReport($query, Carbon $startDate, Carbon $endDate): array
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
     * Generate monthly revenue report
     */
    public function getMonthlyRevenueReport($query, Carbon $startDate, Carbon $endDate): array
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
     * Generate yearly revenue report
     */
    public function getYearlyRevenueReport($query, Carbon $startDate, Carbon $endDate): array
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

    /**
     * Get dashboard statistics for admin
     */
    public function getAdminDashboardStats(): array
    {
        $totalUsers = \App\Models\User::where('role', 'user')->count();
        $totalOwners = \App\Models\User::where('role', 'owner')->count();
        $totalParkingLocations = \App\Models\ParkingLocation::count();
        $totalBookings = ParkingBooking::count();
        $activeBookings = ParkingBooking::whereIn('status', ['upcoming', 'checked_in'])->count();
        $completedBookings = ParkingBooking::where('status', 'completed')->count();
        $cancelledBookings = ParkingBooking::where('status', 'cancelled')->count();

        // Revenue calculation
        $totalRevenue = ParkingBooking::whereIn('status', ['checked_in', 'completed'])->sum('amount');

        return [
            'total_users' => $totalUsers,
            'total_owners' => $totalOwners,
            'total_parking_locations' => $totalParkingLocations,
            'total_bookings' => $totalBookings,
            'active_bookings' => $activeBookings,
            'completed_bookings' => $completedBookings,
            'cancelled_bookings' => $cancelledBookings,
            'total_revenue' => $totalRevenue,
        ];
    }

    /**
     * Get dashboard statistics for owner
     */
    public function getOwnerDashboardStats(int $ownerId): array
    {
        // Get all parking locations owned by this owner
        $parkingLocations = \App\Models\ParkingLocation::where('owner_id', $ownerId)->get();
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

        return [
            'total_parking_locations' => $totalParkingLocations,
            'active_parking_locations' => $activeParkingLocations,
            'total_two_wheeler_capacity' => $totalTwoWheelerCapacity,
            'total_four_wheeler_capacity' => $totalFourWheelerCapacity,
            'total_bookings' => $totalBookings,
            'active_bookings' => $activeBookings,
            'completed_bookings' => $completedBookings,
            'total_revenue' => $totalRevenue,
            'today_revenue' => $todayRevenue,
        ];
    }
}
