<?php

namespace App\Listeners;

use App\Settings\GeneralSettings;
use Pixelated\Streamline\Events\NextAvailableVersionUpdated;

/**
 * @codeCoverageIgnore
 */
readonly class StreamlineNextAvailableVersionUpdatedListener
{
    public function __construct(private GeneralSettings $settings)
    {
    }

    public function __invoke(NextAvailableVersionUpdated $event): void
    {
        $this->settings->availableVersion = $event->version;
        $this->settings->save();
    }
}
