<?php

use App\Actions\SaveCurrentStreamlineVersionInSettings;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Config;
use Mockery\MockInterface;

test('should update settings with current version when config returns valid version', function () {
    /** @var GeneralSettings $mockSettings */
    $mockSettings = $this->mock(GeneralSettings::class,
        fn (MockInterface $mock) => $mock->shouldReceive('save')->once()
    );

    Config::shouldReceive('get')
        ->with('streamline.installed_version')
        ->andReturn('1.2.3');

    $action = new SaveCurrentStreamlineVersionInSettings($mockSettings);

    $action();

    expect($mockSettings->currentVersion)->toEqual('1.2.3');
});
