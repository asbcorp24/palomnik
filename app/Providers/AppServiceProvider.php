<?php

namespace App\Providers;

use App\Models\PilgrimageObject;
use App\Models\SiteColorScheme;
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

        PilgrimageObject::observe(AdminAuditableObserver::class);
        SiteColorScheme::observe(AdminAuditableObserver::class);
        SiteSetting::observe(AdminAuditableObserver::class);
    }
}
