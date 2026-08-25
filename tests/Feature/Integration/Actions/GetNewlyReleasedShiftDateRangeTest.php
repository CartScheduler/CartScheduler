<?php

namespace Tests\Feature\Integration\Actions;

use App\Actions\GetNewlyReleasedShiftDateRange;
use App\Enums\DBPeriod;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\SetConfig;

class GetNewlyReleasedShiftDateRangeTest extends TestCase
{
    use SetConfig;

    public function test_weekly_returns_null_before_release_time(): void
    {
        $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');
        $this->travelTo('2023-02-06 12:29:59');

        $this->assertNull(app(GetNewlyReleasedShiftDateRange::class)->execute());
    }

    public function test_weekly_returns_newly_opened_week_after_release(): void
    {
        $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');
        $this->travelTo('2023-02-06 12:30:01');

        $range = app(GetNewlyReleasedShiftDateRange::class)->execute();

        $this->assertSame('2023-02-13', $range['start']->toDateString());
        $this->assertSame('2023-02-19', $range['end']->toDateString());
    }

    public function test_monthly_returns_next_month_after_release(): void
    {
        $this->setConfig(1, DBPeriod::Month, false, null, '09:00');
        $this->travelTo('2023-01-01 09:00:01');

        $range = app(GetNewlyReleasedShiftDateRange::class)->execute();

        $this->assertSame('2023-02-01', $range['start']->toDateString());
        $this->assertSame('2023-02-28', $range['end']->toDateString());
    }

    public function test_daily_returns_new_max_day_after_release_time(): void
    {
        $this->setConfig(1, DBPeriod::Week, true, 'MON', '08:00');
        $this->travelTo('2023-01-03 08:00:01');

        $range = app(GetNewlyReleasedShiftDateRange::class)->execute();

        $this->assertSame('2023-01-09', $range['start']->toDateString());
        $this->assertSame('2023-01-09', $range['end']->toDateString());
    }

    public function test_force_returns_current_outer_slice_mid_week(): void
    {
        $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');
        $this->travelTo('2023-02-07 10:00:00');

        $this->assertNull(app(GetNewlyReleasedShiftDateRange::class)->execute());

        $range = app(GetNewlyReleasedShiftDateRange::class)->execute(force: true);

        $this->assertSame('2023-02-13', $range['start']->toDateString());
        $this->assertSame('2023-02-19', $range['end']->toDateString());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
