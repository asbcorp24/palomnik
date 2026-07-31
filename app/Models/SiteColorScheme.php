<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SiteColorScheme extends Model
{
    use HasFactory;

    public const COLOR_KEYS = [
        'cream', 'paper', 'gold', 'gold_dark', 'green',
        'green_soft', 'brown', 'ink', 'muted', 'border',
    ];

    protected $fillable = ['name', 'slug', 'colors', 'is_active'];

    protected $casts = [
        'colors' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('site.active_color_scheme'));
        static::deleted(fn () => Cache::forget('site.active_color_scheme'));
    }

    public static function active(): ?self
    {
        return Cache::remember('site.active_color_scheme', 300, function () {
            return static::query()->where('is_active', true)->first()
                ?: static::query()->oldest('id')->first();
        });
    }

    public function activate(): void
    {
        DB::transaction(function (): void {
            static::query()
                ->where('id', '<>', $this->getKey())
                ->update(['is_active' => false]);
            $this->update(['is_active' => true]);
        });

        Cache::forget('site.active_color_scheme');
    }

    public function cssVariables(): array
    {
        $colors = (array) $this->colors;

        return [
            '--pm-cream' => $colors['cream'] ?? '#f7f0e6',
            '--pm-paper' => $colors['paper'] ?? '#fffdf9',
            '--pm-gold' => $colors['gold'] ?? '#b58a32',
            '--pm-gold-dark' => $colors['gold_dark'] ?? '#8f6a20',
            '--pm-green' => $colors['green'] ?? '#26443b',
            '--pm-green-soft' => $colors['green_soft'] ?? '#345d51',
            '--pm-brown' => $colors['brown'] ?? '#6f4d37',
            '--pm-ink' => $colors['ink'] ?? '#211d19',
            '--pm-muted' => $colors['muted'] ?? '#746c64',
            '--pm-border' => $colors['border'] ?? '#d8cfc4',
        ];
    }
}
