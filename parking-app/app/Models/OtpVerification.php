<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtpVerification extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'otp',
        'type',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user that owns the OTP verification.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the OTP is expired.
     */
    public function isExpired()
    {
        return now()->gt($this->expires_at);
    }
}
