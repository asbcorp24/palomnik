<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class AudioGuide extends Model
{
    protected $fillable = [
        'title',
        'path',
        'transcript',
        'original_name',
        'mime_type',
        'size',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    protected $appends = ['url'];

    protected static function booted(): void
    {
        static::deleting(function (AudioGuide $guide): void {
            if ($guide->path) {
                Storage::disk('public')->delete($guide->path);
            }
        });
    }

    public function guideable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getUrlAttribute(): ?string
    {
        return $this->path ? Storage::disk('public')->url($this->path) : null;
    }
}
