<?php

use Bugsnag\BugsnagLaravel\Facades\Bugsnag;

test('tests do not send errors to bugsnag', function () {
    expect(Bugsnag::getConfig()->getAppData()['releaseStage'])->toBe('testing')
        ->and(Bugsnag::shouldNotify())->toBeFalse();
});
