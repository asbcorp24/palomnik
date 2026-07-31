<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    use HasFactory;

    public const SEO_DEFAULTS = [
        'site_name' => 'Московский паломник',
        'default_title' => 'Московский паломник — храмы, монастыри и паломнические маршруты',
        'title_suffix' => 'Московский паломник',
        'default_description' => 'Храмы и монастыри Москвы и Московской области, паломнические маршруты, православные события, святыни и полезная информация для паломников.',
        'default_keywords' => 'храмы Москвы, монастыри Подмосковья, паломничество, православные маршруты, святыни Москвы',
        'canonical_base_url' => null,
        'robots_index' => true,
        'robots_follow' => true,
        'sitemap_enabled' => true,
        'structured_data_enabled' => true,
        'og_type' => 'website',
        'og_image' => null,
        'twitter_card' => 'summary_large_image',
        'twitter_site' => null,
        'google_site_verification' => null,
        'yandex_verification' => null,
        'organization_name' => 'Московский паломник',
        'organization_legal_name' => null,
        'organization_url' => null,
        'organization_logo' => null,
        'organization_phone' => null,
        'organization_email' => null,
        'organization_address' => null,
        'organization_same_as' => null,
    ];

    protected $fillable = ['key', 'value'];

    protected $casts = ['value' => 'array'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('site.settings.seo'));
        static::deleted(fn () => Cache::forget('site.settings.seo'));
    }

    public static function seo(): array
    {
        return Cache::remember('site.settings.seo', 300, function (): array {
            $stored = static::query()->where('key', 'seo')->value('value');
            if (is_string($stored)) {
                $stored = json_decode($stored, true);
            }

            return array_replace(self::SEO_DEFAULTS, is_array($stored) ? $stored : []);
        });
    }

    public static function putSeo(array $value): void
    {
        static::query()->updateOrCreate(
            ['key' => 'seo'],
            ['value' => array_replace(self::SEO_DEFAULTS, $value)]
        );

        Cache::forget('site.settings.seo');
    }
}
