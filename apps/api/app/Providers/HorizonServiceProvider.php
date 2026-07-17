<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn ($user = null): bool => app()->environment('local') || (bool) $user?->is_platform_admin);
    }

    public function boot(): void
    {
        parent::boot();
        Horizon::routeMailNotificationsTo(config('horizon.alert_email'));
    }
}
