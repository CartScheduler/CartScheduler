<?php

use App\Actions\HasNewVersionAvailable;
use App\Settings\GeneralSettings;

beforeEach(function () {
    $this->hasNewVersionAvailable = $this->app->make(HasNewVersionAvailable::class);
    $this->settings = $this->app->make(GeneralSettings::class);
});

test('has new version available is returning correct data', function () {
    $this->settings->currentVersion = '1.0.0';
    $this->settings->availableVersion = '1.0.0';
    $this->settings->save();

    expect($this->hasNewVersionAvailable->execute())->toBeFalse();

    $this->settings->availableVersion = '1.0.1';
    $this->settings->save();

    expect($this->hasNewVersionAvailable->execute())->toBeTrue();

    $this->settings->currentVersion = '';
    $this->settings->save();
    expect($this->hasNewVersionAvailable->execute())->toBeFalse();
    $this->settings->currentVersion = '1.0.0';
    $this->settings->availableVersion = '';
    $this->settings->save();
    expect($this->hasNewVersionAvailable->execute())->toBeFalse();
});
