<?php

namespace App\Providers;

use App\Listeners\StreamlineInstalledVersionSetListener;
use App\Listeners\StreamlineNextAvailableVersionUpdatedListener;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Pixelated\Streamline\Events\InstalledVersionSet;
use Pixelated\Streamline\Events\NextAvailableVersionUpdated;

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

        Event::listen(InstalledVersionSet::class, StreamlineInstalledVersionSetListener::class);
        Event::listen(NextAvailableVersionUpdated::class, StreamlineNextAvailableVersionUpdatedListener::class);
    }
}
