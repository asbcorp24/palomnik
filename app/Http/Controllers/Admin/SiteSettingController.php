<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteColorScheme;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SiteSettingController extends Controller
{
    public function index(): View
    {
        return view('admin.settings.index', [
            'schemes' => SiteColorScheme::query()
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
            'seo' => SiteSetting::seo(),
            'colorKeys' => SiteColorScheme::COLOR_KEYS,
        ]);
    }

    public function storeTheme(Request $request): RedirectResponse
    {
        $data = $this->validateTheme($request);
        $slug = $this->uniqueSlug($data['name']);

        $scheme = SiteColorScheme::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'colors' => $data['colors'],
            'is_active' => false,
        ]);

        if ($request->boolean('activate_after_save')) {
            $scheme->activate();
        }

        return back()->with('success', 'Цветовая схема сохранена.');
    }

    public function updateTheme(Request $request, SiteColorScheme $siteColorScheme): RedirectResponse
    {
        $data = $this->validateTheme($request);
        $siteColorScheme->update([
            'name' => $data['name'],
            'colors' => $data['colors'],
        ]);

        return back()->with('success', 'Цветовая схема обновлена.');
    }

    public function activateTheme(SiteColorScheme $siteColorScheme): RedirectResponse
    {
        $siteColorScheme->activate();

        return back()->with('success', 'Цветовая схема «'.$siteColorScheme->name.'» активирована.');
    }

    public function destroyTheme(SiteColorScheme $siteColorScheme): RedirectResponse
    {
        if ($siteColorScheme->is_active) {
            return back()->with('error', 'Нельзя удалить активную цветовую схему. Сначала активируйте другую.');
        }

        $siteColorScheme->delete();

        return back()->with('success', 'Цветовая схема удалена.');
    }

    public function updateSeo(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:120'],
            'default_title' => ['required', 'string', 'max:255'],
            'title_suffix' => ['nullable', 'string', 'max:120'],
            'default_description' => ['required', 'string', 'max:320'],
            'default_keywords' => ['nullable', 'string', 'max:1000'],
            'canonical_base_url' => ['nullable', 'url', 'max:255'],
            'robots_index' => ['nullable', 'boolean'],
            'robots_follow' => ['nullable', 'boolean'],
            'sitemap_enabled' => ['nullable', 'boolean'],
            'structured_data_enabled' => ['nullable', 'boolean'],
            'og_type' => ['required', Rule::in(['website', 'article'])],
            'og_image' => ['nullable', 'string', 'max:2048'],
            'twitter_card' => ['required', Rule::in(['summary', 'summary_large_image'])],
            'twitter_site' => ['nullable', 'string', 'max:100'],
            'google_site_verification' => ['nullable', 'string', 'max:255'],
            'yandex_verification' => ['nullable', 'string', 'max:255'],
            'organization_name' => ['required', 'string', 'max:255'],
            'organization_legal_name' => ['nullable', 'string', 'max:255'],
            'organization_url' => ['nullable', 'url', 'max:255'],
            'organization_logo' => ['nullable', 'string', 'max:2048'],
            'organization_phone' => ['nullable', 'string', 'max:64'],
            'organization_email' => ['nullable', 'email', 'max:255'],
            'organization_address' => ['nullable', 'string', 'max:500'],
            'organization_same_as' => ['nullable', 'string', 'max:5000'],
        ]);

        foreach (['robots_index', 'robots_follow', 'sitemap_enabled', 'structured_data_enabled'] as $field) {
            $data[$field] = $request->boolean($field);
        }

        $data['canonical_base_url'] = $this->nullableTrimmed($data['canonical_base_url'] ?? null);
        $data['og_image'] = $this->nullableTrimmed($data['og_image'] ?? null);
        $data['organization_logo'] = $this->nullableTrimmed($data['organization_logo'] ?? null);

        SiteSetting::putSeo($data);

        return back()->with('success', 'SEO-настройки сохранены.');
    }

    private function validateTheme(Request $request): array
    {
        $rules = ['name' => ['required', 'string', 'max:120']];
        foreach (SiteColorScheme::COLOR_KEYS as $key) {
            $rules['colors.'.$key] = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }

        $data = $request->validate($rules);
        $data['colors'] = collect($data['colors'])
            ->map(fn ($value) => strtolower((string) $value))
            ->only(SiteColorScheme::COLOR_KEYS)
            ->all();

        return $data;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'theme';
        $slug = $base;
        $counter = 2;

        while (SiteColorScheme::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    private function nullableTrimmed(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
