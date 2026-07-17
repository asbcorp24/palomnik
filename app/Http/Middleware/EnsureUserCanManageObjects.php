<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserCanManageObjects
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        abort_unless($user && $user->is_active && $user->canManageObjects(), 403);

        return $next($request);
    }
}
