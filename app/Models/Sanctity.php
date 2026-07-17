<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Sanctity extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
    ];

    public function pilgrimageObjects(): BelongsToMany
    {
        return $this->belongsToMany(PilgrimageObject::class, 'object_sanctity')
            ->withPivot('note')
            ->withTimestamps();
    }
}
