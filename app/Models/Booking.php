<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'user_id',
        'contact_name',
        'email',
        'phone',
        'participants_count',
        'total_amount',
        'status',
        'payment_status',
        'payment_provider',
        'payment_reference',
        'ticket_code',
        'notes',
    ];

    protected $casts = [
        'participants_count' => 'integer',
        'total_amount' => 'decimal:2',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
