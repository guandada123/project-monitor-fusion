<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            Log::warning('权限拒绝', [
                'user_id'    => $request->user()?->id,
                'email'      => $request->user()?->email,
                'permission' => $permission,
                'route'      => $request->route()?->uri(),
                'method'     => $request->method(),
                'ip'         => $request->ip(),
            ]);
            abort(403, '您没有执行此操作的权限。');
        }

        return $next($request);
    }
}
