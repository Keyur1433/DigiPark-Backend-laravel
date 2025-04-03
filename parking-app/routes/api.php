<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\ParkingLocationController;
use App\Http\Controllers\TimeSlotController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Public parking location routes
Route::get('/parking-locations', [ParkingLocationController::class, 'index']);
Route::get('/parking-locations/{parkingLocation}', [ParkingLocationController::class, 'show']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);

    // User profile routes
    Route::get('/profile', [UserController::class, 'profile']);
    Route::put('/profile', [UserController::class, 'updateProfile']);
    Route::post('/change-password', [UserController::class, 'changePassword']);
    Route::delete('/account', [UserController::class, 'deleteAccount']);

    // Vehicle routes
    Route::apiResource('vehicles', VehicleController::class);

    // Booking routes
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings/check-in', [BookingController::class, 'storeCheckIn']);
    Route::post('/bookings/advance', [BookingController::class, 'storeAdvance']);
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
    Route::post('/bookings/{booking}/complete', [BookingController::class, 'complete']);
    Route::post('/bookings/verify-qr', [BookingController::class, 'verifyQrCode']);

    // Time slot routes
    Route::get('/time-slots', [TimeSlotController::class, 'getAvailableTimeSlots']);
    Route::get('/available-dates', [TimeSlotController::class, 'getAvailableDates']);

    // Owner routes
    Route::prefix('owner')->middleware('role:owner')->group(function () {
        Route::get('/dashboard', [OwnerController::class, 'dashboard']);
        Route::get('/bookings', [OwnerController::class, 'bookings']);
        Route::get('/parking-locations', [OwnerController::class, 'parkingLocations']);
        Route::get('/parking-locations/{parkingLocation}', [OwnerController::class, 'parkingLocationDetails']);
        Route::get('/bookings/{booking}', [OwnerController::class, 'bookingDetails']);
        Route::post('/bookings/{booking}/check-in', [OwnerController::class, 'checkInBooking']);
        Route::post('/bookings/{booking}/check-out', [OwnerController::class, 'checkOutBooking']);
        Route::get('/revenue-reports', [OwnerController::class, 'revenueReports']);
    });

    // Parking location routes (for owners)
    Route::apiResource('parking-locations', ParkingLocationController::class)->except(['index', 'show']);
    Route::post('/parking-locations/{parkingLocation}/toggle-status', [ParkingLocationController::class, 'toggleStatus']);
    Route::get('/my-parking-locations', [ParkingLocationController::class, 'myParkingLocations']);

    // Admin routes
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/users/{user}', [AdminController::class, 'userDetails']);
        Route::get('/parking-locations', [AdminController::class, 'parkingLocations']);
        Route::get('/bookings', [AdminController::class, 'bookings']);
        Route::post('/users/{user}/toggle-status', [AdminController::class, 'toggleUserStatus']);
        Route::post('/parking-locations/{parkingLocation}/toggle-status', [AdminController::class, 'toggleParkingLocationStatus']);
    });
});
