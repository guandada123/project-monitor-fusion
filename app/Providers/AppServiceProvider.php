<?php

namespace App\Providers;

use App\Services\SlowQueryMonitor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // SQL 慢查询监控（>= 500ms 记录日志，>= 1000ms 推送飞书）
        SlowQueryMonitor::boot();

        // API 速率限制
        RateLimiter::for('api', function (Request $request) {
            $limit = $request->user() ? 300 : 60;

            return Limit::perMinute($limit)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'success' => false,
                        'message' => '请求过于频繁，请稍后再试',
                        'errors'  => [],
                    ], 429);
                });
        });
    }
}
