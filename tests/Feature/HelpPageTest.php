<?php

namespace Tests\Feature;

use Tests\TestCase;

class HelpPageTest extends TestCase
{
    public function test_user_help_page_is_publicly_available(): void
    {
        $this->get('/help')
            ->assertOk()
            ->assertSee('Как пользоваться платформой')
            ->assertSee('Быстрый старт')
            ->assertSee('Карта и поиск объектов')
            ->assertSee('Бронирования и билеты');
    }

    public function test_administrator_guide_can_be_opened_from_help_page(): void
    {
        $this->get('/help?section=admin')
            ->assertOk()
            ->assertSee('Руководство по управлению платформой')
            ->assertSee('Храмы, объекты и медиаматериалы')
            ->assertSee('Пользователи и роли')
            ->assertSee('Автоматическое уменьшение изображений');
    }
}
