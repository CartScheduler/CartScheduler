<?php

namespace App\Http\Controllers;

use App\Actions\DoShiftReservation;
use App\Actions\ErrorApiResource;
use App\Actions\ValidateShiftIsAvailableAction;
use App\Actions\ValidateShiftIsNotFullAction;
use App\Actions\ValidateVolunteerIsAllowedToBeRosteredAction;
use App\Exceptions\ShiftAvailabilityException;
use App\Http\Requests\MoveUserToNewShiftRequest;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MoveUserToNewShiftController extends Controller
{
    public function __construct(
        private readonly DoShiftReservation $doShiftReservation,
        private readonly ValidateShiftIsAvailableAction $validateShiftIsAvailableAction,
        private readonly ValidateVolunteerIsAllowedToBeRosteredAction $validateVolunteerIsAllowedToBeRosteredAction,
        private readonly ValidateShiftIsNotFullAction $validateShiftIsNotFullAction,
    ) {}

    public function __invoke(MoveUserToNewShiftRequest $request)
    {
        $oldShift = $request->oldShift();
        $newShift = $request->newShift();

        $date = $request->date('date')?->midDay();
        $userId = $request->integer('user_id');

        $location = Location::where('id', $request->get('location_id'))
            ->where('is_enabled', true)
            ->first();

        try {
            // TODO much of this can be moved into the FormRequest and maybe a custom validation rule
            $this->validateShiftIsAvailableAction->execute($newShift, $date);

            $user = User::find($userId);
            $isAllowed = $this->validateVolunteerIsAllowedToBeRosteredAction->execute($location, $user,
                $newShift->users);
            if (is_string($isAllowed)) {
                return ErrorApiResource::create($isAllowed, ErrorApiResource::CODE_BROTHER_REQUIRED, 422);
            }

            $this->validateShiftIsNotFullAction->execute($newShift, $date);

            DB::transaction(
                function () use ($date, $location, $newShift, $userId, $oldShift) {
                    $oldShift->detachUserOnDate($userId, $date);

                    $this->doShiftReservation->execute($newShift, $location, $userId, $date);
                });
        } catch (ShiftAvailabilityException $e) {
            return ErrorApiResource::create($e->getMessage(), $e->getExceptionType(), 422);
        }

        return response()->noContent();
    }
}
