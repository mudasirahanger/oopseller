<?php

namespace App\Providers;

use App\Services\Amazon\Contracts\SellerDataProvider;
use App\Services\Amazon\SpApiSellerDataProvider;
use App\Services\Rankings\Contracts\RankProvider;
use App\Services\Rankings\NullRankProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SellerDataProvider::class, SpApiSellerDataProvider::class);
        $this->app->bind(RankProvider::class, NullRankProvider::class);
    }

    public function boot(): void
    {
        ResetPassword::createUrlUsing(fn ($notifiable, string $token): string => rtrim((string) config('app.frontend_url'), '/')
            .'/reset-password?token='.$token.'&email='.urlencode($notifiable->getEmailForPasswordReset()));

        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
            Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()),
        ]);
        RateLimiter::for('amazon-oauth', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('api-writes', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
    }
}
