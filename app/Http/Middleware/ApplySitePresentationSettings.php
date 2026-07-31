<?php

namespace App\Http\Middleware;

use App\Models\SiteColorScheme;
use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ApplySitePresentationSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $response;
        }

        $contentType = (string) $response->headers->get('Content-Type');
        if (! str_contains(strtolower($contentType), 'text/html')) {
            return $response;
        }

        $html = (string) $response->getContent();
        if ($html === '' || ! str_contains(strtolower($html), '</head>')) {
            return $response;
        }

        if ($request->is('admin/*') || $request->is('admin')) {
            $response->setContent($this->injectAdminSettingsLink($html, $request));

            return $response;
        }

        if ($request->is('service/*') || $request->is('service')) {
            return $response;
        }

        try {
            if (! Schema::hasTable('site_color_schemes') || ! Schema::hasTable('site_settings')) {
                return $response;
            }

            $scheme = SiteColorScheme::active();
            $seo = SiteSetting::seo();

            if ($scheme) {
                $html = $this->injectTheme($html, $scheme);
            }
            $html = $this->injectSeo($html, $request, $seo);
            $response->setContent($html);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $response;
    }

    private function injectAdminSettingsLink(string $html, Request $request): string
    {
        if (str_contains($html, 'href="'.url('/admin/settings').'"')) {
            return $html;
        }

        $active = $request->is('admin/settings*') ? ' active' : '';
        $link = '<div class="sidebar-label">Настройки</div>'
            .'<a class="sidebar-link'.$active.'" href="'.e(url('/admin/settings')).'">'
            .'<i class="bi bi-sliders"></i><span>Настройки сайта и SEO</span></a>';
        $needle = '<div class="sidebar-label">Справочники</div>';

        return str_contains($html, $needle)
            ? str_replace($needle, $link.$needle, $html)
            : $html;
    }

    private function injectTheme(string $html, SiteColorScheme $scheme): string
    {
        $declarations = collect($scheme->cssVariables())
            ->map(fn (string $value, string $name) => $name.':'.$value)
            ->implode(';');

        $style = '<style id="database-site-color-scheme">:root{'.$declarations.'}'
            .'body{color:var(--pm-ink)}'
            .'.site-header{border-color:var(--pm-border)}'
            .'</style>';

        return str_replace('</head>', $style."\n</head>", $html);
    }

    private function injectSeo(string $html, Request $request, array $seo): string
    {
        $siteName = trim((string) ($seo['site_name'] ?? 'Московский паломник'));
        $title = $this->extractTitle($html)
            ?: trim((string) ($seo['default_title'] ?? $siteName));
        $suffix = trim((string) ($seo['title_suffix'] ?? ''));

        if ($suffix !== '' && ! str_contains(mb_strtolower($title), mb_strtolower($suffix))) {
            $title .= ' — '.$suffix;
            $html = preg_replace(
                '/<title>.*?<\/title>/is',
                '<title>'.e($title).'</title>',
                $html,
                1
            ) ?: $html;
        }

        $description = $this->extractMeta($html, 'description');
        $oldDefault = 'Московский паломник — храмы, святыни, маршруты и паломнические поездки по Москве и Московской области.';
        if ($description === '' || $description === $oldDefault) {
            $description = trim((string) ($seo['default_description'] ?? ''));
            $html = $this->replaceOrAddNamedMeta($html, 'description', $description);
        }

        $base = rtrim((string) ($seo['canonical_base_url'] ?: $request->getSchemeAndHttpHost()), '/');
        $path = '/'.ltrim($request->path(), '/');
        $canonical = $request->path() === '/' ? $base.'/' : $base.$path;
        $robots = ($seo['robots_index'] ? 'index' : 'noindex')
            .','.($seo['robots_follow'] ? 'follow' : 'nofollow');
        $keywords = trim((string) ($seo['default_keywords'] ?? ''));
        $ogImage = $this->absoluteUrl($seo['og_image'] ?? null, $base);
        $twitterSite = trim((string) ($seo['twitter_site'] ?? ''));

        $tags = [];
        $tags[] = '<link rel="canonical" href="'.e($canonical).'">';
        $tags[] = '<meta name="robots" content="'.e($robots).'">';
        if ($keywords !== '') {
            $tags[] = '<meta name="keywords" content="'.e($keywords).'">';
        }
        $tags[] = '<meta property="og:locale" content="ru_RU">';
        $tags[] = '<meta property="og:type" content="'.e((string) ($seo['og_type'] ?? 'website')).'">';
        $tags[] = '<meta property="og:site_name" content="'.e($siteName).'">';
        $tags[] = '<meta property="og:title" content="'.e($title).'">';
        $tags[] = '<meta property="og:description" content="'.e($description).'">';
        $tags[] = '<meta property="og:url" content="'.e($canonical).'">';
        if ($ogImage) {
            $tags[] = '<meta property="og:image" content="'.e($ogImage).'">';
        }
        $tags[] = '<meta name="twitter:card" content="'.e((string) ($seo['twitter_card'] ?? 'summary_large_image')).'">';
        $tags[] = '<meta name="twitter:title" content="'.e($title).'">';
        $tags[] = '<meta name="twitter:description" content="'.e($description).'">';
        if ($twitterSite !== '') {
            $tags[] = '<meta name="twitter:site" content="'.e($twitterSite).'">';
        }
        if ($ogImage) {
            $tags[] = '<meta name="twitter:image" content="'.e($ogImage).'">';
        }
        foreach ([
            'google-site-verification' => $seo['google_site_verification'] ?? null,
            'yandex-verification' => $seo['yandex_verification'] ?? null,
        ] as $name => $value) {
            if (filled($value)) {
                $tags[] = '<meta name="'.$name.'" content="'.e((string) $value).'">';
            }
        }

        if ($seo['structured_data_enabled'] ?? true) {
            $tags[] = $this->structuredData($seo, $base, $siteName);
        }

        return str_replace('</head>', implode("\n", $tags)."\n</head>", $html);
    }

    private function structuredData(array $seo, string $base, string $siteName): string
    {
        $sameAs = preg_split('/\R+/', trim((string) ($seo['organization_same_as'] ?? '')))
            ?: [];
        $sameAs = array_values(array_filter(array_map('trim', $sameAs)));

        $organization = array_filter([
            '@type' => 'Organization',
            '@id' => $base.'/#organization',
            'name' => $seo['organization_name'] ?: $siteName,
            'legalName' => $seo['organization_legal_name'] ?? null,
            'url' => $seo['organization_url'] ?: $base,
            'logo' => $this->absoluteUrl($seo['organization_logo'] ?? null, $base),
            'telephone' => $seo['organization_phone'] ?? null,
            'email' => $seo['organization_email'] ?? null,
            'address' => $seo['organization_address'] ?? null,
            'sameAs' => $sameAs ?: null,
        ], fn ($value) => $value !== null && $value !== '');

        $data = [
            '@context' => 'https://schema.org',
            '@graph' => [
                $organization,
                [
                    '@type' => 'WebSite',
                    '@id' => $base.'/#website',
                    'url' => $base.'/',
                    'name' => $siteName,
                    'publisher' => ['@id' => $base.'/#organization'],
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => $base.'/objects?q={search_term_string}',
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
            ],
        ];

        return '<script type="application/ld+json">'
            .json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            .'</script>';
    }

    private function extractTitle(string $html): string
    {
        preg_match('/<title>(.*?)<\/title>/is', $html, $matches);

        return trim(html_entity_decode(strip_tags($matches[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function extractMeta(string $html, string $name): string
    {
        preg_match('/<meta\s+name=["\']'.preg_quote($name, '/').'["\']\s+content=["\'](.*?)["\'][^>]*>/is', $html, $matches);

        return trim(html_entity_decode($matches[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function replaceOrAddNamedMeta(string $html, string $name, string $content): string
    {
        $tag = '<meta name="'.e($name).'" content="'.e($content).'">';
        $pattern = '/<meta\s+name=["\']'.preg_quote($name, '/').'["\'][^>]*>/is';

        if (preg_match($pattern, $html)) {
            return preg_replace($pattern, $tag, $html, 1) ?: $html;
        }

        return str_replace('</head>', $tag."\n</head>", $html);
    }

    private function absoluteUrl(mixed $value, string $base): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (preg_match('~^https?://~i', $value)) {
            return $value;
        }

        return $base.'/'.ltrim($value, '/');
    }
}
