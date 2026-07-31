<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        DB::table('site_settings')->insert([
            'key' => 'seo',
            'value' => json_encode([
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
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
