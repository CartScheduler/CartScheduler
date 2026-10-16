<?php

namespace App\Actions;

use App\Enums\DBPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

readonly class GetMaxShiftReservationDateAllowed
{
    private DBPeriod $period;

    private mixed $duration;

    private mixed $releaseShiftsOnDay;

    private mixed $releaseShiftsAtTime;

    private mixed $doReleaseShiftsDaily;

    public function __construct(private MapDayOfWeek $mapDayOfWeek)
    {
        $this->period = DBPeriod::getConfigPeriod();
        $this->duration = config('cart-scheduler.shift_reservation_duration');
        $this->releaseShiftsOnDay = config('cart-scheduler.release_weekly_shifts_on_day');
        $this->releaseShiftsAtTime = config('cart-scheduler.release_new_shifts_at_time');
        $this->doReleaseShiftsDaily = config('cart-scheduler.do_release_shifts_daily');
    }

    public function execute(): Carbon
    {
        $now = Carbon::now();

        if ($this->doReleaseShiftsDaily) {
            if ($this->period->value === DBPeriod::Week->value) {
                return $this->negateNumberOfDays($now)
                    ->addWeeks($this->duration)
                    ->endOfDay();
            }

            return $this->negateNumberOfDays($now)
                ->addMonths($this->duration)->endOfDay();
        }

        if ($this->period->value === DBPeriod::Week->value) {
            return $now->startOfWeek($this->mapDayOfWeek->toInteger($this->releaseShiftsOnDay))
                ->when(
                    fn (Carbon $date) => $this->isCurrentDayButBeforeReleaseTime($date, DBPeriod::Week),
                    fn (Carbon $date) => $date->addWeeks($this->duration),
                    fn (Carbon $date) => $date->addWeeks($this->duration + 1),
                )
                ->subDay()
                ->endOfDay();
        }

        return $now
            ->when(
                fn (Carbon $date) => ! $this->isCurrentDayButBeforeReleaseTime($date, DBPeriod::Month),
                fn (Carbon $date) => $date->addMonths($this->duration),
            )
            ->endOfMonth()
            ->endOfDay();
    }

    public function getFailMessage(): string
    {
        if ($this->doReleaseShiftsDaily) {
            if ($this->period->value === DBPeriod::Week->value) {
                return "Sorry, you can only reserve shifts up to $this->duration week(s) in advance.";
            }

            return "Sorry, you can only reserve shifts up to $this->duration month(s) in advance.";
        }

        if ($this->period->value === DBPeriod::Week->value) {
            $time = Carbon::now()->setTimeFromTimeString($this->releaseShiftsAtTime)->format('g:i A');

            return "Sorry, you can only reserve shifts up to $this->duration week(s) in advance, starting each {$this->mapDayOfWeek->lengthen($this->releaseShiftsOnDay)} at $time";
        }

        return "Sorry, you can only reserve shifts up to $this->duration month(s) in advance, after this month";
    }

    /**
     * Determine if the given date is the current day but after the given time.
     * For example, if the current day is Monday and the given time is 10:00 AM,
     * this method will return true if the given date is Monday at 10:00 AM or later.
     */
    private function isCurrentDayButBeforeReleaseTime(Carbon $date, DBPeriod $period): bool
    {
        return $date
            ->when(
                $period === DBPeriod::Week,
                fn (
                    Carbon $date) => $date->startOfWeek($this->mapDayOfWeek->toInteger($this->releaseShiftsOnDay)),
                fn (Carbon $date) => $date->startOfMonth(),
            )
            ->isSameDay($date)
               && $date->setTimeFromTimeString($this->releaseShiftsAtTime)->isFuture();
    }

    private function isBeforeReleaseTime(Carbon $date): bool
    {
        return ! Str::startsWith($this->releaseShiftsAtTime, '00:00')
               && $date->setTimeFromTimeString($this->releaseShiftsAtTime)->isFuture();
    }

    protected function negateNumberOfDays(Carbon $now): Carbon
    {
        return $now->when(
            fn (Carbon $date) => $this->isBeforeReleaseTime($date),
            fn (Carbon $date) => $date->subDays(2),
            fn (Carbon $date) => $date->subDay(),
        );
    }
}
