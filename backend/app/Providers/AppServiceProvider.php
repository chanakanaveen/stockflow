<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

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
        // This application supports cookie sessions only; never query bearer-token storage.
        Sanctum::getAccessTokenFromRequestUsing(fn () => '');
        RateLimiter::for('login', function (Request $request) {
            $email = $request->input('email');
            $identity = is_string($email) ? strtolower(trim($email)) : '';

            return [Limit::perMinute(5)->by(hash('sha256', $identity.'|'.$request->ip())), Limit::perMinute(30)->by('ip:'.$request->ip())];
        });
    }
}
