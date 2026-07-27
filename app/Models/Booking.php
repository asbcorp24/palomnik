<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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
        'ticket_token',
        'checked_in_at',
        'checked_in_by',
        'checked_in_participants',
        'notes',
        'assigned_to',
        'crm_stage',
        'priority',
        'source',
        'contact_attempts',
        'last_contact_at',
        'next_contact_at',
        'confirmed_at',
        'closed_at',
        'internal_notes',
        'cancellation_reason',
    ];

    protected $casts = [
        'participants_count' => 'integer',
        'total_amount' => 'decimal:2',
        'checked_in_at' => 'datetime',
        'checked_in_participants' => 'integer',
        'contact_attempts' => 'integer',
        'last_contact_at' => 'datetime',
        'next_contact_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            $booking->ticket_token = $booking->ticket_token
                ?: hash('sha256', Str::uuid().'|'.microtime(true).'|'.Str::random(32));
        });
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(BookingParticipant::class)->orderByDesc('is_primary')->orderBy('id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(BookingActivity::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['cancelled', 'refunded'], true);
    }

    public function isCheckedIn(): bool
    {
        return $this->checked_in_at !== null;
    }
}
