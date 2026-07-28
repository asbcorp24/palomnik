<?php

namespace Tests\Feature;

use App\Services\FrontendAssetService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FrontendAssetsTest extends TestCase
{
    public function test_external_styles_and_scripts_are_rewritten_to_same_origin_urls(): void
    {
        $html = <<<'HTML'
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
HTML;

        $localized = app(FrontendAssetService::class)->localizeHtml($html);

        $this->assertStringContainsString('/assets/vendor/bootstrap/bootstrap.min.css', $localized);
        $this->assertStringContainsString('/assets/vendor/bootstrap/bootstrap.bundle.min.js', $localized);
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $localized);
        $this->assertStringNotContainsString('fonts.googleapis.com', $localized);
        $this->assertStringNotContainsString('fonts.gstatic.com', $localized);
    }

    public function test_rendered_page_contains_only_same_origin_styles_and_scripts(): void
    {
        $response = $this->get('/privacy')
            ->assertOk()
            ->assertHeader('Content-Security-Policy');

        $html = (string) $response->getContent();

        $this->assertStringContainsString('/assets/vendor/bootstrap/bootstrap.min.css', $html);
        $this->assertStringContainsString('/assets/vendor/bootstrap/bootstrap.bundle.min.js', $html);
        $this->assertStringContainsString('/assets/vendor/bootstrap-icons/bootstrap-icons.min.css', $html);
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $html);
        $this->assertStringNotContainsString('unpkg.com', $html);
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringNotContainsString('fonts.gstatic.com', $html);
    }

    public function test_frontend_asset_is_downloaded_once_and_served_from_local_cache(): void
    {
        Storage::fake('local');
        Http::fake([
            '*' => Http::response(
                '/* locally cached stylesheet used by same-origin frontend asset test */ body { display: block; }',
                200,
                ['Content-Type' => 'text/css']
            ),
        ]);

        $this->get('/assets/vendor/bootstrap/bootstrap.min.css')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/css; charset=UTF-8')
            ->assertSee('locally cached stylesheet', false);

        Storage::disk('local')->assertExists('frontend-assets/bootstrap/bootstrap.min.css');

        $this->get('/assets/vendor/bootstrap/bootstrap.min.css')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_unknown_asset_path_is_not_used_as_an_open_proxy(): void
    {
        $this->get('/assets/vendor/unknown/file.js')->assertNotFound();
    }
}
