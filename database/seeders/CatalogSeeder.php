<?php

namespace Database\Seeders;

use App\Models\ObjectType;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run()
    {
        $types = [
            [
                'name' => 'Храм',
                'slug' => 'temple',
                'marker_color' => '#B08A3E',
                'icon' => 'church',
                'sort_order' => 10,
            ],
            [
                'name' => 'Монастырь',
                'slug' => 'monastery',
                'marker_color' => '#26443B',
                'icon' => 'monastery',
                'sort_order' => 20,
            ],
            [
                'name' => 'Часовня',
                'slug' => 'chapel',
                'marker_color' => '#7A5B43',
                'icon' => 'chapel',
                'sort_order' => 30,
            ],
            [
                'name' => 'Святой источник',
                'slug' => 'holy-spring',
                'marker_color' => '#356E8D',
                'icon' => 'water',
                'sort_order' => 40,
            ],
        ];

        foreach ($types as $type) {
            ObjectType::query()->updateOrCreate(
                ['slug' => $type['slug']],
                $type
            );
        }
    }
}
