<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class PilgrimageRoute extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'category',
        'difficulty',
        'duration_days',
        'duration_minutes',
        'short_description',
        'description',
        'program',
        'base_price',
        'is_group',
        'is_published',
        'published_at',
        'cover_path',
    ];

    protected $casts = [
        'duration_days' => 'integer',
        'duration_minutes' => 'integer',
        'base_price' => 'decimal:2',
        'is_group' => 'boolean',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected $appends = ['cover_url'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
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

    public function objects(): BelongsToMany
    {
        return $this->belongsToMany(PilgrimageObject::class, 'pilgrimage_route_object')
            ->withPivot(['sort_order', 'stay_minutes', 'note'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }
}
