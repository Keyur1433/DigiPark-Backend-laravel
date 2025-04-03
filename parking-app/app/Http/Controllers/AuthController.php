<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\OtpVerification;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    protected $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'contact_number' => $request->contact_number,
            'password' => Hash::make($request->password),
            'state' => $request->state,
            'city' => $request->city,
            'country' => $request->country,
            'role' => $request->role,
        ]);

        // Generate and send OTP
        $this->otpService->generateAndSendOtp($user, 'registration');

        return response()->json([
            'message' => 'User registered successfully. Please verify your account with the OTP sent to your mobile number.',
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * Verify OTP.
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $user = User::where('contact_number', $request->contact_number)->first();

        $otpVerification = OtpVerification::where('user_id', $user->id)
            ->where('otp', $request->otp)
            ->where('type', $request->type)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otpVerification) {
            return response()->json([
                'message' => 'Invalid or expired OTP.',
            ], 422);
        }

        if ($request->type === 'registration') {
            $user->update(['is_verified' => DB::raw('TRUE')]);
        }

        // Delete the used OTP
        $otpVerification->delete();

        return response()->json([
            'message' => 'OTP verified successfully.',
        ]);
    }

    /**
     * Login user.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (!Auth::attempt([
            'contact_number' => $request->contact_number,
            'password' => $request->password,
        ])) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $user = Auth::user();

        if (!$user->is_verified) {
            return response()->json([
                'message' => 'Please verify your account first.',
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    /**
     * Logout user.
     */
    public function logout(): JsonResponse
    {
        Auth::user()->tokens()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Forgot password.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::where('contact_number', $request->contact_number)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        // Generate and send OTP
        $this->otpService->generateAndSendOtp($user, 'password_reset');

        return response()->json([
            'message' => 'OTP sent to your mobile number for password reset.',
        ]);
    }

    /**
     * Reset password.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $user = User::where('contact_number', $request->contact_number)->first();

        $otpVerification = OtpVerification::where('user_id', $user->id)
            ->where('otp', $request->otp)
            ->where('type', 'password_reset')
            ->where('expires_at', '>', now())
            ->first();

        if (!$otpVerification) {
            return response()->json([
                'message' => 'Invalid or expired OTP.',
            ], 422);
        }

        // Update password
        $user->password = Hash::make($request->password);
        $user->save();

        // Delete the used OTP
        $otpVerification->delete();

        return response()->json([
            'message' => 'Password reset successfully.',
        ]);
    }

    /**
     * Get authenticated user.
     */
    public function user(): JsonResponse
    {
        return response()->json([
            'user' => new UserResource(Auth::user()),
        ]);
    }
}
