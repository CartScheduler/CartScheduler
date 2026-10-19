<?php

namespace App\Actions;

use App\Enums\ToggleReservationStatus;
use App\Exceptions\ShiftAvailabilityException;
use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

readonly class ToggleUserOntoShift
{
    public function __construct(private DoShiftReservation $doShiftReservation) {}

    /**
     * Execute the shift reservation or unreserve the shift based on the given data.
     *
     * @param  User  $user  The user performing the shift reservation or un-reservation.
     * @param  array{user?: int, date: string, shift: int, location: int, do_reserve: bool }  $data  The data required for the shift reservation or un-reservation.
     *                                                                                               The array should contain the following keys:
     *                                                                                               - 'user': (optional) The ID of the user to toggle onto the shift. If not provided, the current user ID will be used.
     *                                                                                               - 'date': The date of the shift in the format 'Y-m-d'.
     *                                                                                               - 'shift': The ID of the shift to reserve or unreserve.
     *                                                                                               - 'location': The ID of the location where the shift is located.
     *                                                                                               - 'do_reserve': A boolean flag indicating whether to reserve the shift. If false, the shift will be un-reserved.
     * @return ToggleReservationStatus The status of the shift reservation or un-reservation. Possible values are:
     *                                 - ToggleReservationStatus::RESERVATION_MADE: The shift was successfully reserved.
     *                                 - ToggleReservationStatus::NO_AVAILABLE_SHIFTS: No available shifts to reserve.
     *                                 - ToggleReservationStatus::RESERVATION_REMOVED: The shift was successfully unreserved.
     *
     * @throws LockTimeoutException
     */
    public function execute(User $user, array $data): ToggleReservationStatus
    {
        return Cache::lock('shift_reservation', 10)->block(10, function () use ($user, $data) {
            // If 'data' contains a user key, toggle that user onto a shift. Otherwise, toggle the current user.
            $userIdToToggle = $data['user'] ?? $user->id;
            $shiftDate = Carbon::createFromFormat('Y-m-d', $data['date'])?->setTime(12, 0);
            if (is_null($shiftDate)) {
                throw new RuntimeException("Invalid date format. Expected a format of 'Y-m-d'.");
            }

            /** @var Location $location */
            $location = Location::with([
                // TODO                BUG It looks like the front-end is possibly sending the wrong shift id if the shift day isnt enabled
                'shifts' => fn (HasMany $query) => $query->where('shifts.id', '=', $data['shift']),
                'shifts.users' => fn (BelongsToMany $query) => $query
                    ->wherePivot('shift_date', $shiftDate->toDateString()),
                //                THE QUERY NEEDS TO CHECK IF THE SHIFT IS AVAILABLE AT THE SAME TIME AND THE DAY IS ENABLED. IE THE SHIFT THAT THE ADMIN/USER EXPECTS
                // EG. SHIFT 1 AVAILABLE MON-FRI 9AM - 12 SHIFT 2 AVAILABLE SAT-SUN 9AM - 12. ADMIN ASSIGNS A SHIFT TO A USER FOR SATURDAY, IT LOOKS LIKE THEY'LL BE ASSIGNED TO SHIFT 1 WHEREAS THEY SHOULD BE ASSIGNED TO SHIFT 2
            ])->findOrFail($data['location']);

            /** @var Shift $shift */
            $shift = $location->shifts->first();
            if ($data['do_reserve']) {
                try {
                    $this->doShiftReservation->execute($shift, $location, $userIdToToggle, $shiftDate);
                } catch (ShiftAvailabilityException) {
                    return ToggleReservationStatus::NO_AVAILABLE_SHIFTS;
                }

                return ToggleReservationStatus::RESERVATION_MADE;
            }

            $this->detachUserFromShift($shift, $shiftDate, $userIdToToggle);

            activity()
                ->performedOn($shift)
                ->causedBy($user)
                ->withProperties([
                    'user' => $userIdToToggle,
                    'shift_date' => $shiftDate,
                    'shift.location.name' => $location->name,
                ])
                ->log('shift_unreserved');

            return ToggleReservationStatus::RESERVATION_REMOVED;
        });
    }

    /**
     * @throws RuntimeException
     */
    protected function detachUserFromShift(Shift $shift, bool|Carbon $shiftDate, mixed $userIdToToggle): void
    {
        $removeCount = $shift->detachUserOnDate($userIdToToggle, $shiftDate);
        if (! $removeCount) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException('Could not remove user from shift');
            // @codeCoverageIgnoreEnd
        }
    }
}
