<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_color_schemes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('colors');
            $table->boolean('is_active')->default(false)->index();
            $table->timestamps();
        });

        $now = now();
        DB::table('site_color_schemes')->insert([
            [
                'name' => 'Традиционная',
                'slug' => 'traditional',
                'colors' => json_encode([
                    'cream' => '#f7f0e6', 'paper' => '#fffdf9',
                    'gold' => '#b58a32', 'gold_dark' => '#8f6a20',
                    'green' => '#26443b', 'green_soft' => '#345d51',
                    'brown' => '#6f4d37', 'ink' => '#211d19',
                    'muted' => '#746c64', 'border' => '#d8cfc4',
                ], JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Светлая лаванда',
                'slug' => 'light-lavender',
                'colors' => json_encode([
                    'cream' => '#f7f3fa', 'paper' => '#fffdfd',
                    'gold' => '#c29a45', 'gold_dark' => '#8d6924',
                    'green' => '#59466f', 'green_soft' => '#79628f',
                    'brown' => '#70556d', 'ink' => '#29232d',
                    'muted' => '#746b78', 'border' => '#ddd3e2',
                ], JSON_UNESCAPED_UNICODE),
                'is_active' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Монастырский зелёный',
                'slug' => 'monastery-green',
                'colors' => json_encode([
                    'cream' => '#f1f1e8', 'paper' => '#fffef9',
                    'gold' => '#ad8b43', 'gold_dark' => '#765a1d',
                    'green' => '#183d32', 'green_soft' => '#2d5c4d',
                    'brown' => '#604d36', 'ink' => '#17251f',
                    'muted' => '#68726c', 'border' => '#ccd4cd',
                ], JSON_UNESCAPED_UNICODE),
                'is_active' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Торжественный бордовый',
                'slug' => 'solemn-burgundy',
                'colors' => json_encode([
                    'cream' => '#f8f1ec', 'paper' => '#fffdfa',
                    'gold' => '#bf963f', 'gold_dark' => '#88651f',
                    'green' => '#642d36', 'green_soft' => '#82444e',
                    'brown' => '#65463c', 'ink' => '#2d2022',
                    'muted' => '#796b6b', 'border' => '#decfcd',
                ], JSON_UNESCAPED_UNICODE),
                'is_active' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('site_color_schemes');
    }
};
