<?php

namespace App\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GetShiftFilledData
{
    public function execute(?string $duration = null): array
    {
        $start = Carbon::today();
        $startDate = $start->format('Y-m-d');
        $endDate = (match ($duration) {
            'fortnight' => $start->addWeeks(2)->subDay(),
            default => $start->addMonth()->subDay(),
        })->format('Y-m-d');

        $params = ['startDate' => $startDate, 'endDate' => $endDate];

        $rawQuery = /** @lang MySQL */ 'WITH RECURSIVE dates (date) AS
                 (SELECT :startDate
                  UNION ALL
                  SELECT date + INTERVAL 1 DAY
                  FROM dates
                  WHERE date + INTERVAL 1 DAY <= :endDate)

SELECT dates.date
     , COUNT(shift_user_id) AS shifts_filled
     , CAST(IFNULL(
        (SELECT SUM(locations.max_volunteers)
         FROM shifts
                JOIN locations
                   ON locations.id = shifts.location_id
             AND locations.is_enabled = 1

         WHERE shifts.is_enabled = 1
           AND (shifts.available_from IS NULL OR shifts.available_from <= dates.date)
           AND (shifts.available_to IS NULL OR shifts.available_to >= dates.date)
           AND CASE
                 WHEN DAYOFWEEK(dates.date) = 1 THEN shifts.day_sunday
                 WHEN DAYOFWEEK(dates.date) = 2 THEN shifts.day_monday
                 WHEN DAYOFWEEK(dates.date) = 3 THEN shifts.day_tuesday
                 WHEN DAYOFWEEK(dates.date) = 4 THEN shifts.day_wednesday
                 WHEN DAYOFWEEK(dates.date) = 5 THEN shifts.day_thursday
                 WHEN DAYOFWEEK(dates.date) = 6 THEN shifts.day_friday
                 WHEN DAYOFWEEK(dates.date) = 7 THEN shifts.day_saturday
                 END = 1),
        0) AS UNSIGNED) AS shifts_available
FROM dates
       LEFT JOIN (SELECT shift_user.id AS shift_user_id, shift_user.shift_date AS shift_date
                  FROM shift_user
                         JOIN shifts
                            ON shifts.id = shift_user.shift_id
                      AND shifts.is_enabled = 1
                      AND (shifts.available_from IS NULL OR shifts.available_from <= shift_date)
                      AND (shifts.available_to IS NULL OR shifts.available_to >= shift_date)
                         JOIN locations
                            ON locations.id = shifts.location_id
                      AND locations.is_enabled = 1) AS derived
          ON dates.date = derived.shift_date

GROUP BY dates.date, shifts_available
ORDER BY dates.date';

        return DB::select($rawQuery, $params);
    }
}
