<?php

namespace App\Models;

use App\Services\PilgrimageObjectSearchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PilgrimageObject extends Model
{
    use HasFactory, SoftDeletes;

    public const VERIFICATION_UNVERIFIED = 'unverified';
    public const VERIFICATION_VERIFIED = 'verified';
    public const VERIFICATION_NEEDS_REVIEW = 'needs_review';
    public const VERIFICATION_OUTDATED = 'outdated';
    public const VERIFICATION_PENDING_UPDATE = 'pending_update';

    protected $fillable = [
        'object_type_id',
        'parent_object_id',
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
        'information_verified_at',
        'information_source_url',
        'verified_by',
        'next_verification_at',
        'verification_status',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'information_verified_at' => 'datetime',
        'next_verification_at' => 'datetime',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public static function verificationStatusLabels(): array
    {
        return [
            self::VERIFICATION_UNVERIFIED => 'Не проверено',
            self::VERIFICATION_VERIFIED => 'Подтверждено',
            self::VERIFICATION_NEEDS_REVIEW => 'Требует проверки',
            self::VERIFICATION_OUTDATED => 'Устарело',
            self::VERIFICATION_PENDING_UPDATE => 'Получены изменения',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function objectType(): BelongsTo
    {
        return $this->belongsTo(ObjectType::class);
    }

    public function parentObject(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_object_id');
    }

    public function childObjects(): HasMany
    {
        return $this->hasMany(self::class, 'parent_object_id')
            ->orderBy('name');
    }

    public function publishedChildObjects(): HasMany
    {
        return $this->hasMany(self::class, 'parent_object_id')
            ->published()
            ->orderBy('name');
    }

    public function vicariate(): BelongsTo
    {
        return $this->belongsTo(Vicariate::class);
    }

    public function deanery(): BelongsTo
    {
        return $this->belongsTo(Deanery::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
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

    public function audioGuide(): MorphOne
    {
        return $this->morphOne(AudioGuide::class, 'guideable');
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

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function pointsOfInterest(): HasMany
    {
        return $this->hasMany(PointOfInterest::class)->ordered();
    }

    public function publishedPointsOfInterest(): HasMany
    {
        return $this->hasMany(PointOfInterest::class)->published()->ordered();
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

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->whereIn(
            'object_type_id',
            ObjectType::query()->visible()->select('id')
        );
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->publiclyVisible()
            ->where('is_published', true)
            ->where(function (Builder $query) {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function scopeTypeOrParentOfType(Builder $query, ?string $slug): Builder
    {
        $slug = trim((string) $slug);

        if ($slug === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($slug) {
            $query->whereHas('objectType', fn (Builder $query) => $query->visible()->where('slug', $slug))
                ->orWhereHas('publishedChildObjects', function (Builder $query) use ($slug) {
                    $query->whereHas('objectType', fn (Builder $query) => $query->visible()->where('slug', $slug));
                });
        });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $matchingIds = app(PilgrimageObjectSearchService::class)->matchingIds($term);

        if ($matchingIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereKey($matchingIds);
    }

    public function isInformationCurrent(): bool
    {
        return $this->verification_status === self::VERIFICATION_VERIFIED
            && $this->information_verified_at !== null
            && ($this->next_verification_at === null || $this->next_verification_at->isFuture());
    }
}
