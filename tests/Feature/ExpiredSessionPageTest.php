<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExpiredSessionPageTest extends TestCase
{
    public function test_expired_session_page_has_clear_message_and_navigation(): void
    {
        $html = view('errors.419')->render();

        $this->assertStringContainsString('Время сеанса истекло', $html);
        $this->assertStringContainsString('Войти снова', $html);
        $this->assertStringContainsString('На главную', $html);
        $this->assertStringContainsString(route('login'), $html);
        $this->assertStringContainsString(route('home'), $html);
    }
}
