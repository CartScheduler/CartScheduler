<?php

namespace Tests\Traits;

use App\Enums\DBPeriod;
use Illuminate\Support\Facades\Config;

trait SetConfig
{
    /**
     * @param int $duration A number representing days or months. i.e., 14 could = 14 days or 2 could = 2 months
     * @param \App\Enums\DBPeriod $period Days or Months
     * @param bool $dailyRelease If true, new shifts are released daily
     * @param string|null $dayOfWeekRelease This is only used for month releases. Set to the day shifts are to be released
     * @param string $timeOfDayRelease What time of the day shall new shifts be released?
     * @return void
     */
    protected function setConfig(int $duration, DBPeriod $period, bool $dailyRelease, ?string $dayOfWeekRelease, string $timeOfDayRelease): void
    {
        Config::set('cart-scheduler.shift_reservation_duration', $duration);
        Config::set('cart-scheduler.shift_reservation_duration_period', $period->value);
        Config::set('cart-scheduler.do_release_shifts_daily', $dailyRelease);
        Config::set('cart-scheduler.release_weekly_shifts_on_day', $dayOfWeekRelease ?? 'SUN');
        Config::set('cart-scheduler.release_new_shifts_at_time', $timeOfDayRelease);
    }

}
