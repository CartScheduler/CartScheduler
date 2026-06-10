<?php

namespace App\Actions;

use App\Enums\Appointment;
use App\Enums\ServingAs;
use App\Models\Shift;
use App\Models\ShiftUser;
use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GetAvailableUsersForShift
{
    public function __construct(private readonly GeneralSettings $settings)
    {
    }

    /** @noinspection UnknownColumnInspection */
    public function execute(Shift $shift, Carbon $date, bool $showOnlyAvailable, bool $showOnlyResponsibleBros, bool $hidePublishers, bool $showOnlyElders, bool $showOnlyMinisterialServants): Collection
    {
        $overlappingShifts = $this->getOverlappingShifts($shift, $date);

        $canOnlyBrothersRegister = $this->canOnlyBrothersBook($shift, $date);

        return User::query()
            ->distinct()
            ->select(['users.*', 'last_shift_date', 'last_shift_start_time'])
            ->when($this->settings->enableUserAvailability, fn(Builder $query) => $query
                ->addSelect(['filled_sundays', 'filled_mondays', 'filled_tuesdays', 'filled_wednesdays', 'filled_thursdays', 'filled_fridays', 'filled_saturdays'])
                ->addSelect(['num_sundays', 'num_mondays', 'num_tuesdays', 'num_wednesdays', 'num_thursdays', 'num_fridays', 'num_saturdays', 'comments'])
                ->tap(fn(Builder $query) => $this->getDayCounts($query, $date))
                ->leftJoin(table: 'user_availabilities', first: 'users.id', operator: '=', second: 'user_availabilities.user_id')
            )
            ->leftJoinSub(
                query: DB::query()
                    ->select(['user_id'])
                    ->selectRaw('MAX(shift_date) as last_shift_date')
                    ->selectRaw('MAX(shifts.start_time) as last_shift_start_time')
                    ->from('shift_user')
                    ->join(table: 'shifts', first: fn(JoinClause $join) => $join
                        ->on('shift_user.shift_id', '=', 'shifts.id')
                        ->where('shifts.is_enabled', true)
                    // Note, not "excluding" 'shifts.available_from' and 'shifts.available_to' here, because we want
                    // to include shifts that are disabled by the date.
                    )
                    ->join(table: 'locations', first: fn(JoinClause $join) => $join
                        ->on('shifts.location_id', '=', 'locations.id')
                        ->where('locations.is_enabled', true)
                    )
                    ->groupBy('user_id'),
                as: 'last_shift',
                first: 'last_shift.user_id',
                operator: '=',
                second: 'users.id')
            ->where('users.is_enabled', true)
            ->whereDoesntHave('bookings', fn(Builder $query) => $query
                ->join(table: 'shifts', first: fn(JoinClause $join) => $join
                    ->on('shift_user.shift_id', '=', 'shifts.id')
                    ->where('shifts.is_enabled', true)
                )
                ->join(table: 'locations', first: 'shifts.location_id', operator: '=', second: 'locations.id')
                ->where('shift_date', $date)
                ->where('shifts.is_enabled', true)
                ->where('locations.is_enabled', true)
                ->whereIn('shift_id', $overlappingShifts)
                ->when($canOnlyBrothersRegister, fn(Builder $query) => $query
                    ->where('users.gender', 'male')
                )
            )
            ->when($canOnlyBrothersRegister, fn(Builder $query) => $query
                ->where('users.gender', 'male')
            )
            ->when($this->settings->enableUserAvailability && $showOnlyAvailable, fn(Builder $query) => $query
                ->tap(fn(Builder $query) => $this->queryIsAvailableOnDayOfWeek($query, $date))
                ->tap(fn(Builder $query) => $this->queryIsAvailableAtHour($query, $date, $shift->start_hour))
                ->tap(fn(Builder $query) => $this->queryIsAvailableAtHour($query, $date, $shift->end_hour))
                ->leftJoin(table: 'user_vacations', first: 'users.id', operator: '=', second: 'user_vacations.user_id')
                ->withCount(['vacations as vacations_count' => fn(Builder $query) => $query
                    ->where('start_date', '<=', $date)
                    ->where('end_date', '>=', $date)
                ])
                ->having('vacations_count', '=', 0)
            )
            ->when($this->settings->enableUserLocationChoices && $showOnlyAvailable, fn(Builder $query) => $query
                ->leftJoin(table: 'user_roster_locations', first: 'users.id', operator: '=', second: 'user_roster_locations.user_id')
                ->where('user_roster_locations.location_id', $shift->location_id)
            )
            ->when($showOnlyResponsibleBros, fn(Builder $query) => $query
                ->where('users.responsible_brother', true)
            )
            ->when($hidePublishers, fn(Builder $query) => $query
                ->where('users.serving_as', '!=', ServingAs::Publisher->value)
            )
            ->when($showOnlyElders, fn(Builder $query) => $query
                ->where('users.appointment', '=', Appointment::Elder->value)
            )
            ->when($showOnlyMinisterialServants, fn(Builder $query) => $query
                ->where('users.appointment', '=', Appointment::MinisterialServant->value)
            )
            ->get();
    }

    private function queryIsAvailableOnDayOfWeek(Builder $query, string $date): void
    {
        $query->whereRaw("CASE
                                    WHEN DAYOFWEEK('$date') = 1 THEN user_availabilities.num_sundays
                                    WHEN DAYOFWEEK('$date') = 2 THEN user_availabilities.num_mondays
                                    WHEN DAYOFWEEK('$date') = 3 THEN user_availabilities.num_tuesdays
                                    WHEN DAYOFWEEK('$date') = 4 THEN user_availabilities.num_wednesdays
                                    WHEN DAYOFWEEK('$date') = 5 THEN user_availabilities.num_thursdays
                                    WHEN DAYOFWEEK('$date') = 6 THEN user_availabilities.num_fridays
                                    WHEN DAYOFWEEK('$date') = 7 THEN user_availabilities.num_saturdays
                                    END > 0");
    }

    private function queryIsAvailableAtHour(Builder $query, string $date, int $hour): void
    {
        $query->whereRaw("FIND_IN_SET($hour, CASE
                                    WHEN DAYOFWEEK('$date') = 1 THEN user_availabilities.day_sunday
                                    WHEN DAYOFWEEK('$date') = 2 THEN user_availabilities.day_monday
                                    WHEN DAYOFWEEK('$date') = 3 THEN user_availabilities.day_tuesday
                                    WHEN DAYOFWEEK('$date') = 4 THEN user_availabilities.day_wednesday
                                    WHEN DAYOFWEEK('$date') = 5 THEN user_availabilities.day_thursday
                                    WHEN DAYOFWEEK('$date') = 6 THEN user_availabilities.day_friday
                                    WHEN DAYOFWEEK('$date') = 7 THEN user_availabilities.day_saturday
                                    END)");
    }

    private function getOverlappingShifts(Shift $shift, Carbon $date): \Illuminate\Support\Collection
    {
        return Shift::query()
            ->select(['id'])
            ->where($this->getDayOfWeekForDate($date), true)
            ->where('start_time', '<', $shift->end_time)
            ->where('end_time', '>', $shift->start_time)
            ->get()
            ->map(fn(Shift $shift) => $shift->getKey());
    }

    /**
     * This query is used to get the number of days per week a user has a shift
     * Note, it's not counting the number of shifts per day because it's possible for a user to have multiple shifts on
     * the same day, but it counts the number of shifts per day of the week
     */
    private function getDayCounts(Builder $query, Carbon $date): Builder
    {
        /** @noinspection UnknownColumnInspection */
        return $query->leftJoinSub(DB::query()
            ->selectRaw("user_id")
            ->selectRaw("SUM(num_sundays) AS filled_sundays")
            ->selectRaw("SUM(num_mondays) AS filled_mondays")
            ->selectRaw("SUM(num_tuesdays) AS filled_tuesdays")
            ->selectRaw("SUM(num_wednesdays) AS filled_wednesdays")
            ->selectRaw("SUM(num_thursdays) AS filled_thursdays")
            ->selectRaw("SUM(num_fridays) AS filled_fridays")
            ->selectRaw("SUM(num_saturdays) AS filled_saturdays")
            ->fromSub(fn(\Illuminate\Database\Query\Builder $query) => $query
                ->selectRaw("user_id")
                ->selectRaw("IF(DAYOFWEEK(shift_user.shift_date) = 1, 1, 0) AS num_sundays")
                ->selectRaw("IF(DAYOFWEEK(shift_user.shift_date) = 2, 1, 0) AS num_mondays")
                ->selectRaw("IF(DAYOFWEEK(shift_user.shift_date) = 3, 1, 0) AS num_tuesdays")
                ->selectRaw("IF(DAYOFWEEK(shift_user.shift_date) = 4, 1, 0) AS num_wednesdays")
                ->selectRaw("IF(DAYOFWEEK(shift_user.shift_date) = 5, 1, 0) AS num_thursdays")
                ->selectRaw("IF(DAYOFWEEK(shift_user.shift_date) = 6, 1, 0) AS num_fridays")
                ->selectRaw("IF(DAYOFWEEK(shift_user.shift_date) = 7, 1, 0) AS num_saturdays")
                ->from('shift_user')
                ->join(table: 'shifts', first: 'shift_user.shift_id', operator: '=', second: 'shifts.id')
                ->join(table: 'locations', first: 'shifts.location_id', operator: '=', second: 'locations.id')
                ->whereBetween('shift_date', [$date->clone()->startOfMonth(), $date->clone()->endOfMonth()])
                ->where('shifts.is_enabled', true)
                ->where('locations.is_enabled', true)
                ->groupBy('user_id', 'shift_date')
                , as: "shift_count")
            ->groupBy('user_id'),
            as: "filled_shifts",
            first: "filled_shifts.user_id",
            operator: '=',
            second: 'users.id');
    }

    private function canOnlyBrothersBook(Shift $shift, Carbon $date): bool
    {
        $location = $shift->load('location')->location;
        if (!$location->requires_brother) {
            return false;
        }

        $sistersReservedCount = ShiftUser::with('user')
            ->whereRelation(
                'user',
                fn(Builder $query) => $query->where('gender', '=', 'female')
            )
            ->where('shift_id', $shift->id)
            ->where('shift_date', $date)
            ->count();
        if ($sistersReservedCount < $location->max_volunteers - 1) {
            return false;
        }

        return true;
    }

    /**
     * format of lowercase 'L' ('l') returns the lowercase full day name of the week in English
     */
    private function getDayOfWeekForDate(Carbon $date): string
    {
        return 'day_' . strtolower($date->format('l'));
    }
}
