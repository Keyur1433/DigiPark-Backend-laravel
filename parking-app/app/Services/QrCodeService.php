<?php

namespace App\Services;

use Illuminate\Support\Str;

class QrCodeService
{
    /**
     * Generate a unique QR code for parking bookings
     */
    public function generateQrCode(): string
    {
        // Generate a unique string for QR code
        return Str::random(10) . time();
    }

    /**
     * Validate a QR code
     */
    public function validateQrCode(string $qrCode, string $storedQrCode): bool
    {
        return $qrCode === $storedQrCode;
    }
}
