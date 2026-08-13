<?php

namespace Tests\Feature\Admin;

use App\Models\SiteContentSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteContentSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_edit_site_content_and_public_pages_use_it(): void
    {
        $admin = User::query()->create([
            'name' => 'Администратор контента',
            'email' => 'site-content@example.test',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Внешний вид и тексты сайта')
            ->assertSee('Логотип сайта')
            ->assertSee('Главная страница')
            ->assertSee('Нижняя часть сайта');

        $response = $this->actingAs($admin)->put('/admin/settings/content', [
            'logo_path' => '/image/custom-pilgrim-logo.png',
            'brand_name' => 'Паломник Москвы',
            'header_tagline' => 'Официальный путеводитель',
            'home_eyebrow' => 'Паломническая Москва',
            'home_title' => 'Откройте святыни Москвы',
            'home_lead' => 'Новый текст главной страницы.',
            'home_events_title' => 'Ближайшие православные события',
            'home_events_lead' => 'Новый текст событий.',
            'home_objects_title' => 'Популярные храмы',
            'home_objects_lead' => 'Новый текст блока храмов.',
            'objects_title' => 'Каталог святынь',
            'objects_lead' => 'Новый текст каталога.',
            'routes_title' => 'Маршруты паломника',
            'routes_lead' => 'Новый текст маршрутов.',
            'calendar_title' => 'Православный календарь поездок',
            'calendar_lead' => 'Новый текст календаря.',
            'community_title' => 'Наше сообщество',
            'community_lead' => 'Новый текст сообщества.',
            'footer_tagline' => 'Паломнический сервис Москвы',
            'footer_description' => 'Новый текст в нижней части сайта.',
            'footer_offline_title' => 'Офлайн-доступ',
            'footer_text' => 'Новый текст про работу без сети.',
            'footer_copyright_name' => 'Паломник Москвы',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $saved = SiteContentSetting::values();
        $this->assertSame('Паломник Москвы', $saved['brand_name']);
        $this->assertSame('Откройте святыни Москвы', $saved['home_title']);
        $this->assertSame('Каталог святынь', $saved['objects_title']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Паломник Москвы')
            ->assertSee('Официальный путеводитель')
            ->assertSee('Откройте святыни Москвы')
            ->assertSee('Новый текст главной страницы.')
            ->assertSee('Популярные храмы')
            ->assertSee('Новый текст в нижней части сайта.')
            ->assertSee('custom-pilgrim-logo.png');

        $this->get('/objects')
            ->assertOk()
            ->assertSee('Каталог святынь')
            ->assertSee('Новый текст каталога.');
    }
}
