<?php

namespace App\Providers;

use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use App\Models\Sanctity;
use Illuminate\Database\Eloquent\Builder;
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

        if ($this->app->runningInConsole()) {
            return;
        }

        ObjectType::addGlobalScope('without_holy_springs', function (Builder $query): void {
            $query->where('slug', '<>', 'holy-spring');
        });

        Sanctity::addGlobalScope('without_holy_springs', function (Builder $query): void {
            $query->where('slug', '<>', 'holy-spring');
        });

        PilgrimageObject::addGlobalScope('without_holy_springs', function (Builder $query): void {
            $query->whereDoesntHave('objectType', function (Builder $typeQuery): void {
                $typeQuery->withoutGlobalScope('without_holy_springs')
                    ->where('slug', 'holy-spring');
            });
        });
    }
}
