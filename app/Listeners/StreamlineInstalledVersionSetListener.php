<?php

namespace App\Listeners;

use App\Settings\GeneralSettings;
use Pixelated\Streamline\Events\InstalledVersionSet;

/**
 * @codeCoverageIgnore
 */
readonly class StreamlineInstalledVersionSetListener
{
    public function __construct(private GeneralSettings $settings)
    {
    }

    public function __invoke(InstalledVersionSet $event): void
    {
        $this->settings->currentVersion = $event->version;
        $this->settings->save();
    }
}
