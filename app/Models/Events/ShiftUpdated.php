<?php

namespace App\Models\Events;

use App\Models\Shift;
use App\Models\ShiftUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * @codeCoverageIgnore
 * @deprecated This class has unintended consequences.
 */
class ShiftUpdated
{
    public function __construct(private readonly Shift $shift)
    {
//        $this->deleteOrphanedVolunteerShifts();
    }

    public function deleteOrphanedVolunteerShifts(): void
    {
        // TODO THIS NEEDS PROPER TESTING
        if (
            ($this->shift->available_from && $this->shift->wasChanged('available_from'))
            || ($this->shift->available_to && $this->shift->wasChanged('available_to'))
        ) {
            // Get the shift users that belong to the shift and delete them
            DB::transaction(fn() => ShiftUser::where('shift_id', $this->shift->id)
                ->where(fn(Builder $query) => $query
                    ->where('shift_date', '<', $this->shift->available_from)
                    ->orWhere('shift_date', '>', $this->shift->available_to)
                )
                ->delete()
            );
        }
    }
}
