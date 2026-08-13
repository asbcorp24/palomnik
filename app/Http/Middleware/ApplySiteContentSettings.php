<?php

namespace App\Http\Middleware;

use App\Models\SiteContentSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ApplySiteContentSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $response;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type'));
        $html = (string) $response->getContent();
        if (! str_contains($contentType, 'text/html') || $html === '') {
            return $response;
        }

        try {
            if (! Schema::hasTable('site_seo_settings')) {
                return $response;
            }

            if ($request->is('admin/settings')) {
                $response->setContent($this->injectAdminPanel($html));
                return $response;
            }

            if ($request->is('admin') || $request->is('admin/*') || $request->is('service') || $request->is('service/*')) {
                return $response;
            }

            $response->setContent($this->applyPublicContent($html, $request, $this->content()));
        } catch (Throwable $exception) {
            report($exception);
        }

        return $response;
    }

    private function injectAdminPanel(string $html): string
    {
        if (str_contains($html, 'id="site-content-settings"')) {
            return $html;
        }

        $needle = '<ul class="nav nav-pills settings-tabs gap-2 mb-4" role="tablist">';
        if (! str_contains($html, $needle)) {
            return $html;
        }

        $panel = view('admin.settings._content_panel')->render();

        return str_replace($needle, $panel."\n".$needle, $html);
    }

    private function applyPublicContent(string $html, Request $request, array $content): string
    {
        $brand = e((string) ($content['brand_name'] ?? ''));
        $headerTagline = e((string) ($content['header_tagline'] ?? ''));
        $footerTagline = e((string) ($content['footer_tagline'] ?? ''));
        $footerDescription = e((string) ($content['footer_description'] ?? ''));
        $footerOfflineTitle = e((string) ($content['footer_offline_title'] ?? ''));
        $footerText = e((string) ($content['footer_text'] ?? ''));
        $copyright = e((string) ($content['footer_copyright_name'] ?? $content['brand_name'] ?? ''));

        $html = str_replace(
            '<span class="pm-serif d-block lh-1">Московский паломник</span>',
            '<span class="pm-serif d-block lh-1">'.$brand.'</span>',
            $html
        );
        $html = str_replace(
            '<small class="d-block text-secondary fw-normal mt-1" style="font-size:.68rem;letter-spacing:.08em;text-transform:uppercase">Путеводитель по святыням</small>',
            '<small class="d-block text-secondary fw-normal mt-1" style="font-size:.68rem;letter-spacing:.08em;text-transform:uppercase">'.$headerTagline.'</small>',
            $html
        );
        $html = str_replace(
            '<div class="pm-serif fs-5 text-white">Московский паломник</div>',
            '<div class="pm-serif fs-5 text-white">'.$brand.'</div>',
            $html
        );
        $html = str_replace(
            '<div class="small opacity-75">Единая цифровая платформа паломничества</div>',
            '<div class="small opacity-75">'.$footerTagline.'</div>',
            $html
        );
        $html = str_replace(
            '<p class="small mb-0">Храмы, монастыри, святыни, события и паломнические маршруты по Москве и Московской области.</p>',
            '<p class="small mb-0">'.$footerDescription.'</p>',
            $html
        );
        $html = str_replace(
            '<div class="text-white fw-semibold mb-3">Работа без сети</div>',
            '<div class="text-white fw-semibold mb-3">'.$footerOfflineTitle.'</div>',
            $html
        );
        $html = str_replace(
            '<p class="small mb-3">Карточку выбранного объекта можно сохранить в кэш браузера. Полные офлайн-карты будут реализованы в мобильном приложении.</p>',
            '<p class="small mb-3">'.$footerText.'</p>',
            $html
        );
        $html = str_replace(
            '<span>© '.date('Y').' Московский паломник</span>',
            '<span>© '.date('Y').' '.$copyright.'</span>',
            $html
        );

        $html = $this->applyLogo($html, $content['logo_path'] ?? null);

        if ($request->routeIs('home')) {
            $html = $this->replaceBlock($html, '<div class="hero-eyebrow mb-3">', 'Паломничество по Москве и Подмосковью', $content['home_eyebrow']);
            $html = $this->replaceBlock($html, '<h1 class="hero-title mb-4">', 'Святые места становятся ближе', $content['home_title']);
            $html = $this->replaceBlock($html, '<p class="hero-lead mb-4">', 'Найдите храм, узнайте о святынях и расписании, выберите событие, подготовьте маршрут и получите электронный билет.', $content['home_lead']);
            $html = $this->replaceBlock($html, '<h2 class="section-title mb-2">', 'События паломника', $content['home_events_title']);
            $html = $this->replaceBlock($html, '<p class="section-lead mb-0">', 'Богослужения, праздники, крестные ходы, встречи и организованные поездки.', $content['home_events_lead']);
            $html = $this->replaceBlock($html, '<h2 class="section-title mb-2">', 'Храмы и монастыри', $content['home_objects_title']);
            $html = $this->replaceBlock($html, '<p class="section-lead mb-0">', 'Карточки объектов наполняются через административную панель и используются сайтом и мобильным API.', $content['home_objects_lead']);
            $html = $this->replaceBlock($html, '<h2 class="section-title mb-3">', 'Что вы ищете сегодня?', $content['home_categories_title']);
            $html = $this->replaceBlock($html, '<p class="section-lead mx-auto mb-0">', 'Фильтры помогают быстро перейти к нужному типу паломнического объекта.', $content['home_categories_lead']);
            $html = $this->replaceBlock($html, '<h2 class="section-title mb-3">', 'От интереса — к паломничеству', $content['home_steps_title']);
            $html = $this->replaceBlock($html, '<p class="section-lead mb-0">', 'Выберите место или событие, забронируйте поездку и сохраните QR-билет в телефоне.', $content['home_steps_lead']);
        }

        if ($request->routeIs('objects.index')) {
            $html = $this->replaceBlock($html, '<h1 class="section-title mb-3">', 'Храмы и монастыри', $content['objects_title']);
            $html = $this->replaceBlock($html, '<p class="section-lead mb-0">', 'Ищите объекты по названию, адресу, типу, викариатству и благочинию. Регистр букв и небольшие опечатки не влияют на результат.', $content['objects_lead']);
        }

        if ($request->routeIs('routes.index')) {
            $html = $this->replaceBlock($html, '<h1 class="section-title mb-3">', 'Паломнические маршруты', $content['routes_title']);
            $html = $this->replaceBlock($html, '<p class="section-lead mb-0">', 'Однодневные, многодневные, тематические, семейные и молодёжные программы.', $content['routes_lead']);
            $html = $this->replaceBlock($html, '<h2 class="h2 mb-3">', 'Составьте маршрут дня', $content['routes_builder_title']);
            $html = $this->replaceBlock($html, '<p class="text-secondary mb-0">', 'Укажите местоположение, свободное время, способ передвижения и интересующую тему. Система подберёт храмы и монастыри, проверит расписания и рассчитает путь.', $content['routes_builder_lead']);
        }

        if ($request->routeIs('calendar.index')) {
            $html = $this->replaceBlock($html, '<h1 class="section-title mb-3">', 'Календарь паломника', $content['calendar_title']);
            $html = $this->replaceBlock($html, '<p class="section-lead mb-0">', 'Богослужения, праздники, крестные ходы, лекции, семейные встречи и организованные поездки.', $content['calendar_lead']);
        }

        if ($request->routeIs('community.index')) {
            $html = $this->replaceBlock($html, '<h1 class="section-title mb-3">', 'Сообщество паломников', $content['community_title']);
            $html = $this->replaceBlock($html, '<p class="section-lead mb-0">', 'Находите единомышленников для совместного паломничества, делитесь путевыми заметками, фотографиями и отзывами о святых местах.', $content['community_lead']);
        }

        if ($request->routeIs('map')) {
            $html = $this->replaceBlock($html, '<h1 class="h2 mb-3">', 'Храмы и монастыри', $content['map_title']);
            $html = str_replace(
                'Карта загружает только объекты в видимой области. Приближайте карту, чтобы увидеть отдельные храмы и точки интереса.',
                e((string) $content['map_lead']),
                $html
            );
        }

        return $html;
    }

    private function replaceBlock(string $html, string $opening, string $default, mixed $value): string
    {
        $closing = str_starts_with($opening, '<p ') ? '</p>' : (str_starts_with($opening, '<div ') ? '</div>' : (str_starts_with($opening, '<h1 ') ? '</h1>' : '</h2>'));

        return str_replace($opening.$default.$closing, $opening.e((string) $value).$closing, $html);
    }

    private function applyLogo(string $html, mixed $path): string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return $html;
        }

        $url = preg_match('~^https?://~i', $path) ? $path : asset(ltrim($path, '/'));
        $mark = '<span class="brand-mark"><i class="bi bi-cross"></i></span>';
        $image = '<span class="brand-mark site-custom-logo"><img src="'.e($url).'" alt="" loading="eager"></span>';
        $html = str_replace($mark, $image, $html);

        if (! str_contains($html, 'id="site-custom-logo-style"')) {
            $style = '<style id="site-custom-logo-style">.site-custom-logo{background:#fff;padding:3px}.site-custom-logo img{width:100%;height:100%;object-fit:contain;border-radius:inherit}</style>';
            $html = str_replace('</head>', $style."\n</head>", $html);
        }

        return $html;
    }

    private function content(): array
    {
        return array_replace([
            'home_eyebrow' => 'Паломничество по Москве и Подмосковью',
            'home_categories_title' => 'Что вы ищете сегодня?',
            'home_categories_lead' => 'Фильтры помогают быстро перейти к нужному типу паломнического объекта.',
            'home_steps_title' => 'От интереса — к паломничеству',
            'home_steps_lead' => 'Выберите место или событие, забронируйте поездку и сохраните QR-билет в телефоне.',
            'routes_builder_title' => 'Составьте маршрут дня',
            'routes_builder_lead' => 'Укажите местоположение, свободное время, способ передвижения и интересующую тему. Система подберёт храмы и монастыри, проверит расписания и рассчитает путь.',
            'map_title' => 'Храмы и монастыри',
            'map_lead' => 'Карта загружает только объекты в видимой области. Приближайте карту, чтобы увидеть отдельные храмы и точки интереса.',
            'footer_offline_title' => 'Работа без сети',
            'footer_copyright_name' => 'Московский паломник',
        ], SiteContentSetting::values());
    }
}
