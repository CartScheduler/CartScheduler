<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * The installed version now comes from the VERSION file written by each deployment.
     */
    public function up(): void
    {
        $this->migrator->deleteIfExists('general.currentVersion');
        $this->migrator->deleteIfExists('general.availableVersion');
    }
};
