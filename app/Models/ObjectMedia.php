<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ObjectMedia extends Model
{
    use HasFactory;

    protected $table = 'object_media';

    protected $fillable = [
        'pilgrimage_object_id',
        'type',
        'path',
        'external_url',
        'title',
        'description',
        'sort_order',
        'is_cover',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_cover' => 'boolean',
    ];

    protected $appends = [
        'url',
    ];

    public function pilgrimageObject(): BelongsTo
    {
        return $this->belongsTo(PilgrimageObject::class);
    }

    public function getUrlAttribute(): ?string
    {
        if ($this->external_url) {
            return $this->external_url;
        }

        return $this->path ? Storage::disk('public')->url($this->path) : null;
    }
}
