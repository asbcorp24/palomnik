<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\CalendarEvent;
use App\Models\PilgrimageObject;
use App\Models\PilgrimageRoute;
use App\Models\SiteSetting;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $seo = SiteSetting::seo();
        abort_unless($seo['sitemap_enabled'] ?? true, 404);

        $base = rtrim((string) ($seo['canonical_base_url'] ?: url('/')), '/');
        $urls = collect([
            ['loc' => $base.'/', 'priority' => '1.0', 'changefreq' => 'daily'],
            ['loc' => $base.'/map', 'priority' => '0.9', 'changefreq' => 'daily'],
            ['loc' => $base.'/objects', 'priority' => '0.9', 'changefreq' => 'daily'],
            ['loc' => $base.'/routes', 'priority' => '0.9', 'changefreq' => 'daily'],
            ['loc' => $base.'/route-of-the-day', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['loc' => $base.'/calendar', 'priority' => '0.8', 'changefreq' => 'daily'],
            ['loc' => $base.'/community', 'priority' => '0.7', 'changefreq' => 'daily'],
        ]);

        PilgrimageObject::query()->published()
            ->select(['slug', 'updated_at'])
            ->orderBy('id')
            ->chunk(500, function ($items) use ($urls, $base): void {
                foreach ($items as $item) {
                    $urls->push([
                        'loc' => $base.'/objects/'.rawurlencode($item->slug),
                        'lastmod' => optional($item->updated_at)->toAtomString(),
                        'priority' => '0.8',
                        'changefreq' => 'weekly',
                    ]);
                }
            });

        PilgrimageRoute::query()->published()
            ->select(['slug', 'updated_at'])
            ->orderBy('id')
            ->chunk(500, function ($items) use ($urls, $base): void {
                foreach ($items as $item) {
                    $urls->push([
                        'loc' => $base.'/routes/'.rawurlencode($item->slug),
                        'lastmod' => optional($item->updated_at)->toAtomString(),
                        'priority' => '0.8',
                        'changefreq' => 'weekly',
                    ]);
                }
            });

        CalendarEvent::query()->published()
            ->select(['slug', 'updated_at'])
            ->orderBy('id')
            ->chunk(500, function ($items) use ($urls, $base): void {
                foreach ($items as $item) {
                    $urls->push([
                        'loc' => $base.'/calendar/'.rawurlencode($item->slug),
                        'lastmod' => optional($item->updated_at)->toAtomString(),
                        'priority' => '0.7',
                        'changefreq' => 'weekly',
                    ]);
                }
            });

        BlogPost::query()
            ->where('status', 'published')
            ->where(function ($query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->select(['slug', 'updated_at'])
            ->orderBy('id')
            ->chunk(500, function ($items) use ($urls, $base): void {
                foreach ($items as $item) {
                    $urls->push([
                        'loc' => $base.'/community/'.rawurlencode($item->slug),
                        'lastmod' => optional($item->updated_at)->toAtomString(),
                        'priority' => '0.6',
                        'changefreq' => 'monthly',
                    ]);
                }
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $item) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.$this->xml((string) $item['loc'])."</loc>\n";
            if (! empty($item['lastmod'])) {
                $xml .= '    <lastmod>'.$this->xml((string) $item['lastmod'])."</lastmod>\n";
            }
            $xml .= '    <changefreq>'.$item['changefreq']."</changefreq>\n";
            $xml .= '    <priority>'.$item['priority']."</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $seo = SiteSetting::seo();
        $base = rtrim((string) ($seo['canonical_base_url'] ?: url('/')), '/');
        $allowIndexing = (bool) ($seo['robots_index'] ?? true);
        $lines = [
            'User-agent: *',
            $allowIndexing ? 'Allow: /' : 'Disallow: /',
            'Disallow: /admin/',
            'Disallow: /service/',
            'Disallow: /profile/',
            'Disallow: /notifications',
        ];

        if ($seo['sitemap_enabled'] ?? true) {
            $lines[] = 'Sitemap: '.$base.'/sitemap.xml';
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
