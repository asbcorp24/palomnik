<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vicariate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    public function deaneries(): HasMany
    {
        return $this->hasMany(Deanery::class);
    }

    public function pilgrimageObjects(): HasMany
    {
        return $this->hasMany(PilgrimageObject::class);
    }
}
