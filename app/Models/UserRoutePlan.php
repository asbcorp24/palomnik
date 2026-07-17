<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UserRoutePlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'transport_mode',
        'estimated_minutes',
        'notes',
    ];

    protected $casts = [
        'estimated_minutes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function objects(): BelongsToMany
    {
        return $this->belongsToMany(PilgrimageObject::class, 'user_route_plan_object')
            ->withPivot(['sort_order', 'stay_minutes'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }
}
