<?php

namespace App\Models;

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
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function pilgrimageObjects(): HasMany
    {
        return $this->hasMany(PilgrimageObject::class);
    }
}
