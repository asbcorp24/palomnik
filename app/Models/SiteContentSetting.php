<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteContentSetting extends Model
{
    public const DEFAULTS = [
        'logo_path' => null,
        'brand_name' => 'Московский паломник',
        'header_tagline' => 'Путеводитель по святыням',
        'home_title' => 'Святые места становятся ближе',
        'home_lead' => 'Найдите храм, узнайте о святынях и расписании, выберите событие, подготовьте маршрут и получите электронный билет.',
        'home_events_title' => 'События паломника',
        'home_events_lead' => 'Богослужения, праздники, крестные ходы, встречи и организованные поездки.',
        'home_objects_title' => 'Храмы и монастыри',
        'home_objects_lead' => 'Карточки объектов наполняются через административную панель и используются сайтом и мобильным API.',
        'objects_title' => 'Храмы и монастыри',
        'objects_lead' => 'Ищите объекты по названию, адресу, типу, викариатству и благочинию. Регистр букв и небольшие опечатки не влияют на результат.',
        'routes_title' => 'Паломнические маршруты',
        'routes_lead' => 'Однодневные, многодневные, тематические, семейные и молодёжные программы.',
        'calendar_title' => 'Календарь паломника',
        'calendar_lead' => 'Богослужения, праздники, крестные ходы, лекции, семейные встречи и организованные поездки.',
        'community_title' => 'Сообщество паломников',
        'community_lead' => 'Находите единомышленников для совместного паломничества, делитесь путевыми заметками, фотографиями и отзывами о святых местах.',
        'footer_tagline' => 'Единая цифровая платформа паломничества',
        'footer_description' => 'Храмы, монастыри, святыни, события и паломнические маршруты по Москве и Московской области.',
        'footer_text' => 'Карточку выбранного объекта можно сохранить в кэш браузера. Полные офлайн-карты будут реализованы в мобильном приложении.',
    ];

    protected $table = 'site_seo_settings';
    protected $fillable = ['key', 'value'];
    protected $casts = ['value' => 'array'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('site.settings.content'));
        static::deleted(fn () => Cache::forget('site.settings.content'));
    }

    public static function values(): array
    {
        return Cache::remember('site.settings.content', 300, function (): array {
            $stored = static::query()->where('key', 'content')->value('value');
            if (is_string($stored)) {
                $stored = json_decode($stored, true);
            }

            return array_replace(self::DEFAULTS, is_array($stored) ? $stored : []);
        });
    }

    public static function put(array $value): void
    {
        static::query()->updateOrCreate(
            ['key' => 'content'],
            ['value' => array_replace(self::DEFAULTS, $value)]
        );
        Cache::forget('site.settings.content');
    }
}
