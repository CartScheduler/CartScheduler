<?php

namespace App\Actions;

use App\Enums\DBPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

readonly class GetNewlyReleasedShiftDateRange
{
    public function __construct(private MapDayOfWeek $mapDayOfWeek)
    {
    }

    /**
     * @return array{start: Carbon, end: Carbon}|null
     */
    public function execute(bool $force = false): ?array
    {
        $now = Carbon::now();

        if (!$force && !$this->isReleaseMoment($now)) {
            return null;
        }

        if ($this->doReleaseShiftsDaily()) {
            return $this->dailyRange($now);
        }

        if ($this->period()->value === DBPeriod::Week->value) {
            return $this->weeklyRange($now);
        }

        return $this->monthlyRange($now);
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private function dailyRange(Carbon $now): array
    {
        $max = $this->negateNumberOfDays($now->copy())
            ->when(
                $this->period()->value === DBPeriod::Week->value,
                fn(Carbon $date) => $date->addWeeks($this->duration()),
                fn(Carbon $date) => $date->addMonths($this->duration()),
            )
            ->endOfDay();

        return [
            'start' => $max->copy()->startOfDay(),
            'end'   => $max,
        ];
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private function weeklyRange(Carbon $now): array
    {
        $weekStart    = $now->copy()->startOfWeek($this->mapDayOfWeek->toInteger($this->releaseShiftsOnDay()));
        $periodOffset = $this->isAfterReleaseForCurrentPeriod($now) ? $this->duration() : max(0, $this->duration() - 1);

        return [
            'start' => $weekStart->copy()->addWeeks($periodOffset)->startOfDay(),
            'end'   => $weekStart->copy()->addWeeks($periodOffset + 1)->subDay()->endOfDay(),
        ];
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private function monthlyRange(Carbon $now): array
    {
        $monthStart = $now->copy()->startOfMonth();

        if ($this->isAfterReleaseForCurrentPeriod($now)) {
            return [
                'start' => $monthStart->copy()->addMonth()->startOfDay(),
                'end'   => $monthStart->copy()->addMonths($this->duration())->endOfMonth()->endOfDay(),
            ];
        }

        return [
            'start' => $monthStart->copy()->startOfDay(),
            'end'   => $monthStart->copy()->endOfMonth()->endOfDay(),
        ];
    }

    private function isReleaseMoment(Carbon $now): bool
    {
        if ($this->doReleaseShiftsDaily()) {
            return !$this->isBeforeReleaseTime($now->copy());
        }

        if ($this->period()->value === DBPeriod::Week->value) {
            $weekStart = $now->copy()->startOfWeek($this->mapDayOfWeek->toInteger($this->releaseShiftsOnDay()));

            return $weekStart->isSameDay($now) && !$this->isBeforeReleaseTime($now->copy());
        }

        return $now->day === 1 && !$this->isBeforeReleaseTime($now->copy());
    }

    private function isAfterReleaseForCurrentPeriod(Carbon $now): bool
    {
        if ($this->doReleaseShiftsDaily()) {
            return !$this->isBeforeReleaseTime($now->copy());
        }

        if ($this->period()->value === DBPeriod::Week->value) {
            $weekStart = $now->copy()->startOfWeek($this->mapDayOfWeek->toInteger($this->releaseShiftsOnDay()));

            if (!$weekStart->isSameDay($now)) {
                return true;
            }

            return !$this->isBeforeReleaseTime($now->copy());
        }

        if ($now->day !== 1) {
            return true;
        }

        return !$this->isBeforeReleaseTime($now->copy());
    }

    private function isBeforeReleaseTime(Carbon $date): bool
    {
        $releaseShiftsAtTime = $this->releaseShiftsAtTime();

        if (Str::startsWith($releaseShiftsAtTime, '00:00')) {
            return false;
        }

        $releaseAt = $date->copy()->setTimeFromTimeString($releaseShiftsAtTime);

        return $date->lt($releaseAt);
    }

    private function negateNumberOfDays(Carbon $now): Carbon
    {
        return $now->when(
            fn(Carbon $date) => $this->isBeforeReleaseTime($date),
            fn(Carbon $date) => $date->subDays(2),
            fn(Carbon $date) => $date->subDay(),
        );
    }

    private function period(): DBPeriod
    {
        return DBPeriod::getConfigPeriod();
    }

    private function duration(): int
    {
        return (int) config('cart-scheduler.shift_reservation_duration');
    }

    private function releaseShiftsOnDay(): string
    {
        return (string) config('cart-scheduler.release_weekly_shifts_on_day');
    }

    private function releaseShiftsAtTime(): string
    {
        return (string) config('cart-scheduler.release_new_shifts_at_time');
    }

    private function doReleaseShiftsDaily(): mixed
    {
        return config('cart-scheduler.do_release_shifts_daily');
    }
}
