<?php

namespace App\Actions;

use App\Exceptions\ShiftAvailabilityException;
use App\Models\Shift;
use Illuminate\Support\Carbon;

class ValidateShiftIsAvailableAction
{
    /**
     * @throws ShiftAvailabilityException
     */
    public function execute(Shift $shift, Carbon $wantedDate): void
    {
        // Only care about the date, so set the time to midday
        $wantedDate->midday();
        if ($shift->available_from || $shift->available_to) {
            if ($shift->available_from && $wantedDate->isBefore(Carbon::createFromTimeString("{$shift->available_from}T00:00:00"))) {
                throw ShiftAvailabilityException::notAvailableYet();
            }

            if ($shift->available_to && $wantedDate->isAfter(Carbon::createFromTimeString("{$shift->available_to}T23:59:59"))) {
                throw ShiftAvailabilityException::notAvailableAnymore();
            }
        }
    }
}
