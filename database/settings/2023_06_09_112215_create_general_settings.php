<?php

use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        /** @see https://github.com/spatie/laravel-settings/#operations-in-group */
        $this->migrator->inGroup('general', function (SettingsBlueprint $blueprint): void {
            $blueprint->add('siteName', config('app.name'));
            $blueprint->add('currentVersion', config('cart-scheduler.version'));
            $blueprint->add('availableVersion', config('cart-scheduler.version'));
            $blueprint->add('allowedSettingsUsers', [1]);
        });
    }
};
