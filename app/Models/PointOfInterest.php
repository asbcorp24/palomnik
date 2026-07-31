<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PointOfInterest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'points_of_interest';

    public const CATEGORIES = [
        'attraction' => [
            'label' => 'Точка интереса',
            'icon' => 'bi-star',
            'color' => '#7c3aed',
        ],
        'parking' => [
            'label' => 'Стоянка',
            'icon' => 'bi-p-circle',
            'color' => '#2563eb',
        ],
        'cafe' => [
            'label' => 'Кафе',
            'icon' => 'bi-cup-hot',
            'color' => '#b45309',
        ],
        'hotel' => [
            'label' => 'Гостиница',
            'icon' => 'bi-building',
            'color' => '#0f766e',
        ],
    ];

    protected $fillable = [
        'pilgrimage_object_id',
        'category',
        'name',
        'description',
        'address',
        'latitude',
        'longitude',
        'phone',
        'website',
        'schedule_text',
        'is_published',
        'sort_order',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_published' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'category_label',
        'category_icon',
        'marker_color',
    ];

    public function pilgrimageObject(): BelongsTo
    {
        return $this->belongsTo(PilgrimageObject::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category]['label'] ?? 'Точка интереса';
    }

    public function getCategoryIconAttribute(): string
    {
        return self::CATEGORIES[$this->category]['icon'] ?? 'bi-geo-alt';
    }

    public function getMarkerColorAttribute(): string
    {
        return self::CATEGORIES[$this->category]['color'] ?? '#7c3aed';
    }
}
