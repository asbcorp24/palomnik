<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;

class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $achievements = [
            [
                'title' => 'Начинающий паломник',
                'slug' => 'beginner-pilgrim',
                'category' => 'visits',
                'badge_level' => 'bronze',
                'points' => 50,
                'condition_type' => 'visits_count',
                'condition_value' => 5,
                'description' => 'Посетить 5 храмов.',
                'icon' => 'bi-award',
            ],
            [
                'title' => 'Активный паломник',
                'slug' => 'active-pilgrim',
                'category' => 'visits',
                'badge_level' => 'silver',
                'points' => 200,
                'condition_type' => 'visits_count',
                'condition_value' => 20,
                'description' => 'Посетить 20 храмов.',
                'icon' => 'bi-award-fill',
            ],
            [
                'title' => 'Опытный паломник',
                'slug' => 'experienced-pilgrim',
                'category' => 'visits',
                'badge_level' => 'gold',
                'points' => 500,
                'condition_type' => 'visits_count',
                'condition_value' => 50,
                'description' => 'Посетить 50 храмов.',
                'icon' => 'bi-trophy-fill',
            ],
            [
                'title' => 'Сергиев путь',
                'slug' => 'sergius-way',
                'category' => 'thematic_route',
                'badge_level' => 'special',
                'points' => 300,
                'condition_type' => 'route_completed',
                'condition_value' => 1,
                'description' => 'Посетить все храмы маршрута, связанного с преподобным Сергием Радонежским.',
                'icon' => 'bi-signpost-split',
            ],
            [
                'title' => 'Места Патриарха Тихона',
                'slug' => 'patriarch-tikhon-places',
                'category' => 'thematic_route',
                'badge_level' => 'special',
                'points' => 300,
                'condition_type' => 'route_completed',
                'condition_value' => 1,
                'description' => 'Пройти маршрут по храмам, где совершал богослужения святитель Тихон.',
                'icon' => 'bi-cross',
            ],
            [
                'title' => 'Новомученики',
                'slug' => 'new-martyrs',
                'category' => 'thematic_route',
                'badge_level' => 'special',
                'points' => 250,
                'condition_type' => 'route_completed',
                'condition_value' => 1,
                'description' => 'Пройти маршрут по местам памяти новомучеников.',
                'icon' => 'bi-bookmark-star',
            ],
            [
                'title' => 'Семейный паломник',
                'slug' => 'family-pilgrim',
                'category' => 'family_trips',
                'badge_level' => 'special',
                'points' => 250,
                'condition_type' => 'family_trips_count',
                'condition_value' => 5,
                'description' => 'Совершить 5 семейных паломнических поездок.',
                'icon' => 'bi-people-fill',
            ],
        ];

        foreach ($achievements as $achievement) {
            Achievement::query()->updateOrCreate(
                ['slug' => $achievement['slug']],
                $achievement + ['is_active' => true]
            );
        }
    }
}
