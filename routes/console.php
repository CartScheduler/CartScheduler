<?php

use App\Actions\MapDayOfWeek;
use App\Enums\DBPeriod;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Schedule::command('activitylog:clean --force')->daily();
Schedule::command('cart-scheduler:has-update')->daily();

if (config('cart-scheduler.shift_assignment_notifications_enabled')) {
    $releaseTime = config('cart-scheduler.release_new_shifts_at_time');
    $releaseTime = strlen($releaseTime) === 5 ? $releaseTime : substr($releaseTime, 0, 5);

    if (config('cart-scheduler.do_release_shifts_daily')) {
        Schedule::command('cart-scheduler:notify-released-shifts')->dailyAt($releaseTime);
    } elseif (DBPeriod::getConfigPeriod() === DBPeriod::Week) {
        Schedule::command('cart-scheduler:notify-released-shifts')
            ->weeklyOn(
                app(MapDayOfWeek::class)->toInteger(config('cart-scheduler.release_weekly_shifts_on_day')),
                $releaseTime,
            );
    } else {
        Schedule::command('cart-scheduler:notify-released-shifts')->monthlyOn(1, $releaseTime);
    }
}
