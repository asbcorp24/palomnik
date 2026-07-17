<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Achievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'category',
        'badge_level',
        'points',
        'condition_type',
        'condition_value',
        'description',
        'icon',
        'is_active',
    ];

    protected $casts = [
        'points' => 'integer',
        'condition_value' => 'integer',
        'is_active' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->withPivot(['awarded_at', 'progress'])
            ->withTimestamps();
    }
}
