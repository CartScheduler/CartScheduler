<?php

use App\Actions\GetNewlyReleasedShiftDateRange;
use App\Enums\DBPeriod;
use Illuminate\Support\Carbon;
use Tests\Traits\SetConfig;

uses(SetConfig::class);

afterEach(function () {
    Carbon::setTestNow();
});

test('weekly returns null before release time', function () {
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');
    $this->travelTo('2023-02-06 12:29:59');

    expect(app(GetNewlyReleasedShiftDateRange::class)->execute())->toBeNull();
});

test('weekly returns newly opened week after release', function () {
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');
    $this->travelTo('2023-02-06 12:30:01');

    $range = app(GetNewlyReleasedShiftDateRange::class)->execute();

    $this->assertSame('2023-02-13', $range['start']->toDateString());
    $this->assertSame('2023-02-19', $range['end']->toDateString());
});

test('monthly returns next month after release', function () {
    $this->setConfig(1, DBPeriod::Month, false, null, '09:00');
    $this->travelTo('2023-01-01 09:00:01');

    $range = app(GetNewlyReleasedShiftDateRange::class)->execute();

    $this->assertSame('2023-02-01', $range['start']->toDateString());
    $this->assertSame('2023-02-28', $range['end']->toDateString());
});

test('daily returns new max day after release time', function () {
    $this->setConfig(1, DBPeriod::Week, true, 'MON', '08:00');
    $this->travelTo('2023-01-03 08:00:01');

    $range = app(GetNewlyReleasedShiftDateRange::class)->execute();

    $this->assertSame('2023-01-09', $range['start']->toDateString());
    $this->assertSame('2023-01-09', $range['end']->toDateString());
});

test('force returns current outer slice mid week', function () {
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');
    $this->travelTo('2023-02-07 10:00:00');

    expect(app(GetNewlyReleasedShiftDateRange::class)->execute())->toBeNull();

    $range = app(GetNewlyReleasedShiftDateRange::class)->execute(force: true);

    $this->assertSame('2023-02-13', $range['start']->toDateString());
    $this->assertSame('2023-02-19', $range['end']->toDateString());
});
