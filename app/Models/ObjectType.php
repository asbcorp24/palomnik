<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ObjectType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'marker_color',
        'icon',
        'sort_order',
        'is_active',
        'is_public',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('is_public', true);
    }

    public function pilgrimageObjects(): HasMany
    {
        return $this->hasMany(PilgrimageObject::class);
    }
}
