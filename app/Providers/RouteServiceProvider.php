<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    // protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::prefix('api/v1')
                ->name('api.v1.')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/map_viewport.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/points_of_interest.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/site_settings.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/information_audit.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/day_route.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/catalog_management.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/admin_activity.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/analytics.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
