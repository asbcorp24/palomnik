<?php

use App\Http\Controllers\Admin\SiteSettingController;
use App\Http\Controllers\Site\SeoController;
use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');

Route::prefix('admin/settings')
    ->name('admin.settings.')
    ->middleware(['auth', 'verified', 'admin'])
    ->group(function (): void {
        Route::get('/', [SiteSettingController::class, 'index'])->name('index');
        Route::post('/themes', [SiteSettingController::class, 'storeTheme'])->name('themes.store');
        Route::put('/themes/{siteColorScheme}', [SiteSettingController::class, 'updateTheme'])->name('themes.update');
        Route::put('/themes/{siteColorScheme}/activate', [SiteSettingController::class, 'activateTheme'])->name('themes.activate');
        Route::delete('/themes/{siteColorScheme}', [SiteSettingController::class, 'destroyTheme'])->name('themes.destroy');
        Route::put('/seo', [SiteSettingController::class, 'updateSeo'])->name('seo.update');
    });
