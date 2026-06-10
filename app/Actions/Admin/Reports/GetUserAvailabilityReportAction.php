<?php

namespace App\Actions\Admin\Reports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * WIP, ignore in coverage until it's done
 * @codeCoverageIgnore
 */
class GetUserAvailabilityReportAction
{
    public function execute(Carbon $startDate, Carbon $endDate): Collection
    {
        $startDateString = $startDate->toDateString();
        $endDateString = $endDate->toDateString();

        return DB::table('users AS u')
            ->select([
                'u.id AS uid',
                'u.name',
                'u.email',
                'u.mobile_phone',
                'u.gender',
                DB::raw('IF(u.password IS null, "no", "yes") AS account_confirmed'),
                DB::raw("(
                    SELECT COUNT(*) FROM shift_user
                    WHERE shift_user.user_id = u.id
                    AND shift_user.shift_date BETWEEN '$startDateString' AND '$endDateString'
                ) AS shift_count"),
                DB::raw('IF(
                    (SELECT COUNT(*) FROM user_availabilities
                    WHERE user_availabilities.user_id = u.id
                ) = 1, "yes", "no") AS availability_set'),
                DB::raw('CASE
                    WHEN ua.day_monday IS NOT NULL AND ua.num_mondays > 0 THEN
                        CONCAT(
                            LPAD(SUBSTRING_INDEX(ua.day_monday, ",", 1), 2, "0"), ":00-",
                            LPAD(SUBSTRING_INDEX(ua.day_monday, ",", -1), 2, "0"), ":00"
                        )
                    ELSE NULL
                END AS availability_monday'),
                DB::raw('CASE
                    WHEN ua.day_tuesday IS NOT NULL AND ua.num_tuesdays > 0 THEN
                        CONCAT(
                            LPAD(SUBSTRING_INDEX(ua.day_tuesday, ",", 1), 2, "0"), ":00-",
                            LPAD(SUBSTRING_INDEX(ua.day_tuesday, ",", -1), 2, "0"), ":00"
                        )
                    ELSE NULL
                END AS availability_tuesday'),
                DB::raw('CASE
                    WHEN ua.day_wednesday IS NOT NULL AND ua.num_wednesdays > 0 THEN
                        CONCAT(
                            LPAD(SUBSTRING_INDEX(ua.day_wednesday, ",", 1), 2, "0"), ":00-",
                            LPAD(SUBSTRING_INDEX(ua.day_wednesday, ",", -1), 2, "0"), ":00"
                        )
                    ELSE NULL
                END AS availability_wednesday'),
                DB::raw('CASE
                    WHEN ua.day_thursday IS NOT NULL AND ua.num_thursdays > 0 THEN
                        CONCAT(
                            LPAD(SUBSTRING_INDEX(ua.day_thursday, ",", 1), 2, "0"), ":00-",
                            LPAD(SUBSTRING_INDEX(ua.day_thursday, ",", -1), 2, "0"), ":00"
                        )
                    ELSE NULL
                END AS availability_thursday'),
                DB::raw('CASE
                    WHEN ua.day_friday IS NOT NULL AND ua.num_fridays > 0 THEN
                        CONCAT(
                            LPAD(SUBSTRING_INDEX(ua.day_friday, ",", 1), 2, "0"), ":00-",
                            LPAD(SUBSTRING_INDEX(ua.day_friday, ",", -1), 2, "0"), ":00"
                        )
                    ELSE NULL
                END AS availability_friday'),
                DB::raw('CASE
                    WHEN ua.day_saturday IS NOT NULL AND ua.num_saturdays > 0 THEN
                        CONCAT(
                            LPAD(SUBSTRING_INDEX(ua.day_saturday, ",", 1), 2, "0"), ":00-",
                            LPAD(SUBSTRING_INDEX(ua.day_saturday, ",", -1), 2, "0"), ":00"
                        )
                    ELSE NULL
                END AS availability_saturday'),
                DB::raw('CASE
                    WHEN ua.day_sunday IS NOT NULL AND ua.num_sundays > 0 THEN
                        CONCAT(
                            LPAD(SUBSTRING_INDEX(ua.day_sunday, ",", 1), 2, "0"), ":00-",
                            LPAD(SUBSTRING_INDEX(ua.day_sunday, ",", -1), 2, "0"), ":00"
                        )
                    ELSE NULL
                END AS availability_sunday'),
                'ua.updated_at AS last_updated_availability',
            ])
            ->leftJoin('user_availabilities AS ua', 'u.id', '=', 'ua.user_id')
            ->orderByDesc('shift_count')
            ->get();
    }
}
