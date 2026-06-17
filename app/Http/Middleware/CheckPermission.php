<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request — check for a single permission.
     *
     * Usage: Route::middleware('permission:view_dashboard')...
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! $request->user() || ! $request->user()->hasPermission($permission)) {
            abort(403, '您没有执行此操作的权限。');
        }

        return $next($request);
    }
}
