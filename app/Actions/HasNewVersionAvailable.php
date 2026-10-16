<?php

namespace App\Actions;

use App\Settings\GeneralSettings;

class HasNewVersionAvailable
{
    public function __construct(private readonly GeneralSettings $settings) {}

    public function execute(): bool
    {
        $current = $this->settings->currentVersion;
        $available = $this->settings->availableVersion;
        if (empty($current) || empty($available)) {
            return false;
        }

        return version_compare($current, $available, '<');
    }
}
