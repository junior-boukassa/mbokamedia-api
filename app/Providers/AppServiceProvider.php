<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\Paginator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        JsonResource::withoutWrapping();
        Paginator::useBootstrapFive();

        RateLimiter::for('admin-login', static function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email'));
            $key = sprintf('%s|%s', $email, $request->ip());

            return Limit::perMinute(config('admin.login.rate_limit'))
                ->by($key)
                ->response(static function () {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many login attempts. Please try again in a minute.',
                        'data' => null,
                    ], 429);
                });
        });

        RateLimiter::for('api', static function (Request $request): Limit {
            $key = (string) ($request->user()?->getAuthIdentifier() ?: $request->ip());

            return Limit::perMinute((int) env('API_RATE_LIMIT', 60))
                ->by($key)
                ->response(static function () {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many requests. Please slow down.',
                        'data' => null,
                    ], 429);
                });
        });
    }
}
