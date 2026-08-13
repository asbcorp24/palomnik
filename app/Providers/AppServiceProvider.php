<?php

namespace App\Providers;

use App\Http\Middleware\ApplySiteContentSettings;
use App\Models\PilgrimageObject;
use App\Models\SiteColorScheme;
use App\Models\SiteContentSetting;
use App\Models\SiteSetting;
use App\Observers\AdminAuditableObserver;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        app('router')->pushMiddlewareToGroup('web', ApplySiteContentSettings::class);

        PilgrimageObject::observe(AdminAuditableObserver::class);
        SiteColorScheme::observe(AdminAuditableObserver::class);
        SiteContentSetting::observe(AdminAuditableObserver::class);
        SiteSetting::observe(AdminAuditableObserver::class);
    }
}
