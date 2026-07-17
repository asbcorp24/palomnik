<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarEvent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'pilgrimage_object_id',
        'pilgrimage_route_id',
        'trip_id',
        'created_by',
        'title',
        'slug',
        'type',
        'short_description',
        'description',
        'starts_at',
        'ends_at',
        'all_day',
        'location',
        'address',
        'latitude',
        'longitude',
        'capacity',
        'registration_url',
        'contact_phone',
        'contact_email',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'all_day' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'capacity' => 'integer',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public static function typeLabels(): array
    {
        return [
            'service' => 'Богослужение',
            'feast' => 'Престольный праздник',
            'procession' => 'Крестный ход',
            'pilgrimage' => 'Паломническая поездка',
            'lecture' => 'Лекция или встреча',
            'family' => 'Семейное мероприятие',
            'youth' => 'Молодёжное мероприятие',
            'charity' => 'Благотворительное событие',
            'other' => 'Другое',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function pilgrimageObject(): BelongsTo
    {
        return $this->belongsTo(PilgrimageObject::class);
    }

    public function pilgrimageRoute(): BelongsTo
    {
        return $this->belongsTo(PilgrimageRoute::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->where(function (Builder $query) {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->where('ends_at', '>=', now())
                ->orWhere(function (Builder $query) {
                    $query->whereNull('ends_at')->where('starts_at', '>=', now());
                });
        });
    }

    public function typeLabel(): string
    {
        return static::typeLabels()[$this->type] ?? $this->type;
    }
}
