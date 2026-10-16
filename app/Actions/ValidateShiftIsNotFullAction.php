<?php

namespace App\Actions;

use App\Exceptions\ShiftAvailabilityException;
use App\Models\Shift;
use Illuminate\Support\Carbon;

class ValidateShiftIsNotFullAction
{
    /**
     * @throws ShiftAvailabilityException
     */
    public function execute(Shift $shift, Carbon $date): void
    {
        $shift->loadMissing(['location', 'users']);

        $assignedVolunteers = $shift->users()->wherePivot('shift_date', $date->toDateString())->count();
        $maxVolunteers = $shift->location->max_volunteers;

        if ($assignedVolunteers >= $maxVolunteers) {
            throw ShiftAvailabilityException::shiftIsFull();
        }
    }
}
