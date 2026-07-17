<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deanery extends Model
{
    use HasFactory;

    protected $fillable = [
        'vicariate_id',
        'name',
        'slug',
        'description',
    ];

    public function vicariate(): BelongsTo
    {
        return $this->belongsTo(Vicariate::class);
    }

    public function pilgrimageObjects(): HasMany
    {
        return $this->hasMany(PilgrimageObject::class);
    }
}
