<?php

namespace App\Actions;

use Illuminate\Support\Str;
use InvalidArgumentException;

class MapDayOfWeek
{
    public function toInteger(string $dayOfWeek): int
    {
        $dayOfWeek = Str::upper($dayOfWeek);

        if (! in_array($dayOfWeek, ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'])) {
            throw new InvalidArgumentException('Invalid day of week');
        }

        return array_search($dayOfWeek, ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT']);
    }

    public function lengthen(string $dayOfWeek): string
    {
        $dayOfWeek = Str::upper($dayOfWeek);

        return match ($dayOfWeek) {
            'SUN' => 'Sunday',
            'MON' => 'Monday',
            'TUE' => 'Tuesday',
            'WED' => 'Wednesday',
            'THU' => 'Thursday',
            'FRI' => 'Friday',
            'SAT' => 'Saturday',
            default => throw new InvalidArgumentException('Invalid day of week'),
        };
    }
}
