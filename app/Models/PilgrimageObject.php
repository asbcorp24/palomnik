<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PilgrimageObject extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'object_type_id',
        'vicariate_id',
        'deanery_id',
        'name',
        'slug',
        'short_description',
        'description',
        'history',
        'address',
        'latitude',
        'longitude',
        'phone',
        'email',
        'website',
        'schedule_text',
        'parking_info',
        'accessibility_info',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function objectType(): BelongsTo
    {
        return $this->belongsTo(ObjectType::class);
    }

    public function vicariate(): BelongsTo
    {
        return $this->belongsTo(Vicariate::class);
    }

    public function deanery(): BelongsTo
    {
        return $this->belongsTo(Deanery::class);
    }

    public function sanctities(): BelongsToMany
    {
        return $this->belongsToMany(Sanctity::class, 'object_sanctity')
            ->withPivot('note')
            ->withTimestamps();
    }

    public function routes(): BelongsToMany
    {
        return $this->belongsToMany(PilgrimageRoute::class, 'pilgrimage_route_object')
            ->withPivot(['sort_order', 'stay_minutes', 'note'])
            ->withTimestamps();
    }

    public function media(): HasMany
    {
        return $this->hasMany(ObjectMedia::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function coverMedia(): HasOne
    {
        return $this->hasOne(ObjectMedia::class)
            ->where('is_cover', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function representatives(): HasMany
    {
        return $this->hasMany(ObjectRepresentative::class);
    }

    public function approvedRepresentatives(): HasMany
    {
        return $this->representatives()->where('status', 'approved');
    }

    public function updateRequests(): HasMany
    {
        return $this->hasMany(ObjectUpdateRequest::class);
    }

    public function mediaSubmissions(): HasMany
    {
        return $this->hasMany(ObjectMediaSubmission::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function userMedia(): HasMany
    {
        return $this->hasMany(UserMedia::class);
    }

    public function favoriteLists(): BelongsToMany
    {
        return $this->belongsToMany(FavoriteList::class, 'favorite_list_object')
            ->withTimestamps();
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

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term) {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('address', 'like', "%{$term}%")
                ->orWhere('short_description', 'like', "%{$term}%")
                ->orWhereHas('sanctities', function (Builder $query) use ($term) {
                    $query->where('name', 'like', "%{$term}%");
                });
        });
    }
}
