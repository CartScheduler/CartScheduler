<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
        DB::prohibitDestructiveCommands(app()->isProduction());

        Schema::defaultStringLength(191);
        if (config('app.is_https')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('mail-notifications', static fn () => Limit::perMinute(
            max(1, (int) config('cart-scheduler.mail_rate_per_minute'))
        ));
    }
}
