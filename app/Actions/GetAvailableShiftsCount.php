<?php

namespace App\Actions;

use App\Data\AvailableShiftMetaData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class GetAvailableShiftsCount
{
    /**
     * Returns 3 properties for each date in the range: volunteer_count, max_volunteers, has_availability
     * - volunteer_count: the number of volunteers already signed up for a shift on that date
     * - max_volunteers: the maximum number of volunteers allowed for a shift on that date
     * - has_availability: a boolean indicating whether there is still availability for a shift on that date
     *
     * Parameters line up with those in cart-scheduler config
     *
     * @param  string  $startDate  YYYY-MM-DD
     * @param  string  $endDate  YYYY-MM-DD
     *
     * @return \Illuminate\Support\Collection
     */
    public function execute(string $startDate, string $endDate): Collection
    {
        $query = /** @lang MySQL */
            "WITH RECURSIVE dates (date) AS
                 (SELECT :startDate
                  UNION ALL
                  SELECT date + INTERVAL 1 DAY
                  FROM dates
                  WHERE date + INTERVAL 1 DAY <= :endDate)

SELECT dates.date
     , IFNULL(
        (SELECT CAST(SUM(l.max_volunteers) AS UNSIGNED)
         FROM locations l
                JOIN shifts s
                   ON s.location_id = l.id
             AND s.is_enabled = TRUE
             AND (
                        s.available_from IS NULL
                          OR s.available_from <= dates.date
                        )
             AND (
                        s.available_to IS NULL
                          OR s.available_to >= dates.date
                        )
         WHERE l.is_enabled = TRUE
           AND CASE
                 WHEN DAYOFWEEK(dates.date) = 1 THEN s.day_sunday
                 WHEN DAYOFWEEK(dates.date) = 2 THEN s.day_monday
                 WHEN DAYOFWEEK(dates.date) = 3 THEN s.day_tuesday
                 WHEN DAYOFWEEK(dates.date) = 4 THEN s.day_wednesday
                 WHEN DAYOFWEEK(dates.date) = 5 THEN s.day_thursday
                 WHEN DAYOFWEEK(dates.date) = 6 THEN s.day_friday
                 WHEN DAYOFWEEK(dates.date) = 7 THEN s.day_saturday
                 END = 1), 0)                                           AS max_allowed
     , IFNULL(count_query.vc, 0)                                        AS volunteer_count
     , IF((SELECT volunteer_count) < (SELECT max_allowed), TRUE, FALSE) AS has_availability
FROM dates
       LEFT JOIN (SELECT shift_date
                  , COUNT(*) AS vc
             FROM shift_user
                    JOIN shifts s
                       ON s.id = shift_user.shift_id
                 AND s.is_enabled = TRUE
                 AND (
                            s.available_from IS NULL
                              OR s.available_from <= shift_date
                            )
                 AND (
                            s.available_to IS NULL
                              OR s.available_to >= shift_date
                            )
                    JOIN locations l
                       ON l.id = s.location_id
                 AND l.is_enabled = TRUE
             WHERE CASE
                     WHEN DAYOFWEEK(shift_date) = 1 THEN s.day_sunday
                     WHEN DAYOFWEEK(shift_date) = 2 THEN s.day_monday
                     WHEN DAYOFWEEK(shift_date) = 3 THEN s.day_tuesday
                     WHEN DAYOFWEEK(shift_date) = 4 THEN s.day_wednesday
                     WHEN DAYOFWEEK(shift_date) = 5 THEN s.day_thursday
                     WHEN DAYOFWEEK(shift_date) = 6 THEN s.day_friday
                     WHEN DAYOFWEEK(shift_date) = 7 THEN s.day_saturday
                     END = 1
             GROUP BY shift_date) AS count_query
          ON count_query.shift_date = dates.date


GROUP BY dates.date, count_query.vc
ORDER BY dates.date";

        $params = ['startDate' => $startDate, 'endDate' => $endDate];

        $results = DB::select($query, $params);
        return collect($results)
            ->keyBy(fn(stdClass $shift) => $shift->date)
            ->map(fn(stdClass $shift) => AvailableShiftMetaData::from($shift))
            ;
    }
}
