<?php

namespace App\Services;

use App\Models\OtpVerification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class OtpService
{
    /**
     * Generate and send OTP to user
     */
    public function generateAndSendOtp(User $user, string $type): string
    {
        // Generate a 6-digit OTP
        $otp = $this->generateOtp();

        // Delete any existing OTPs for this user and type
        OtpVerification::where('user_id', $user->id)
            ->where('type', $type)
            ->delete();

        // Create new OTP record
        OtpVerification::create([
            'user_id' => $user->id,
            'otp' => $otp,
            'type' => $type,
            'expires_at' => Carbon::now()->addMinutes(10), // OTP valid for 10 minutes
        ]);

        // In a real application, you would send the OTP via SMS here
        // For now, we'll just log it
        Log::info("OTP for user {$user->id} ({$user->contact_number}): {$otp}");

        return $otp;
    }

    /**
     * Generate a random 6-digit OTP
     */
    private function generateOtp(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(User $user, string $otp, string $type): bool
    {
        $otpVerification = OtpVerification::where('user_id', $user->id)
            ->where('otp', $otp)
            ->where('type', $type)
            ->where('expires_at', '>', now())
            ->first();

        return $otpVerification !== null;
    }

    /**
     * Resend OTP
     */
    public function resendOtp(User $user, string $type): string
    {
        // Check if there's a recent OTP that was sent less than 1 minute ago
        $recentOtp = OtpVerification::where('user_id', $user->id)
            ->where('type', $type)
            ->where('created_at', '>', Carbon::now()->subMinute())
            ->first();

        if ($recentOtp) {
            // Return the existing OTP if it was sent less than 1 minute ago
            return $recentOtp->otp;
        }

        // Generate and send a new OTP
        return $this->generateAndSendOtp($user, $type);
    }
}
