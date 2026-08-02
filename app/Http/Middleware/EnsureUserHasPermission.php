<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        abort_unless(
            $user && $user->is_active && $user->hasPermission($permission),
            403,
            'Недостаточно прав для выполнения этого действия.'
        );

        return $next($request);
    }
}
