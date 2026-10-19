<?php

namespace App\Actions;

use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Config;

readonly class SaveCurrentStreamlineVersionInSettings
{
    public function __construct(private GeneralSettings $settings) {}

    public function __invoke(): void
    {
        $currentVersion = Config::get('streamline.installed_version');

        if ($currentVersion) {
            $this->settings->currentVersion = $currentVersion;
            $this->settings->save();
        }
    }
}
