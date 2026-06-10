<?php

namespace App\Providers;

use App\Listeners\StreamlineInstalledVersionSetListener;
use App\Listeners\StreamlineNextAvailableVersionUpdatedListener;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Pixelated\Streamline\Events\InstalledVersionSet;
use Pixelated\Streamline\Events\NextAvailableVersionUpdated;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    /** @noinspection StaticClosureCanBeUsedInspection */
    public function boot(): void
    {
        Cache::macro('flexibleWithEnum',
            /**
             * TODO Delete this after Laravel fixes 'flexible' to accept enums as keys.
             *  This will happen after Laravel v12:46
             *
             * Retrieve an item from the cache by key, refreshing it in the background if it is stale.
             *
             * @template TCacheValue
             *
             * @param  BackedEnum  $key
             * @param  array{ 0: \DateTimeInterface|\DateInterval|int, 1: \DateTimeInterface|\DateInterval|int }  $ttl
             * @param  (callable(): TCacheValue)  $callback
             * @param  array{ seconds?: int, owner?: string }|null  $lock
             * @param  bool  $alwaysDefer
             * @return TCacheValue
             */
            fn(
                BackedEnum $key,
                array $ttl,
                callable $callback,
                ?array $lock = null,
                bool $alwaysDefer = false
            ) => Cache::flexible($key->value, $ttl, $callback, $lock, $alwaysDefer)
        );

        Model::preventLazyLoading(!app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(!app()->isProduction());

        Schema::defaultStringLength(191);
        if (config('app.is_https')) {
            URL::forceScheme('https');
        }

        Event::listen(InstalledVersionSet::class, StreamlineInstalledVersionSetListener::class);
        Event::listen(NextAvailableVersionUpdated::class, StreamlineNextAvailableVersionUpdatedListener::class);
    }
}
