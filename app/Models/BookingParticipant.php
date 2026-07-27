<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'full_name',
        'phone',
        'email',
        'birth_date',
        'decision_status',
        'attendance_status',
        'is_primary',
        'paid_amount',
        'notes',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'is_primary' => 'boolean',
        'paid_amount' => 'decimal:2',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
