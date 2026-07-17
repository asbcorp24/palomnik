<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trip extends Model
{
    use HasFactory;

    protected $fillable = [
        'pilgrimage_route_id',
        'title',
        'starts_at',
        'ends_at',
        'meeting_point',
        'capacity',
        'booked_count',
        'price',
        'status',
        'notes',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'capacity' => 'integer',
        'booked_count' => 'integer',
        'price' => 'decimal:2',
    ];

    public function pilgrimageRoute(): BelongsTo
    {
        return $this->belongsTo(PilgrimageRoute::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
