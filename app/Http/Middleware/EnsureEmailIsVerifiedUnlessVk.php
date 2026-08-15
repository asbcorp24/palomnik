<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;

class EnsureEmailIsVerifiedUnlessVk extends EnsureEmailIsVerified
{
    public function handle($request, Closure $next, $redirectToRoute = null)
    {
        if ($request->user() && filled($request->user()->vk_id)) {
            return $next($request);
        }

        return parent::handle($request, $next, $redirectToRoute);
    }
}
