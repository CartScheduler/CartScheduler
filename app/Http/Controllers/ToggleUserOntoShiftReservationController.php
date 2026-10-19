<?php

namespace App\Http\Controllers;

use App\Actions\ErrorApiResource;
use App\Actions\ToggleUserOntoShift;
use App\Enums\ToggleReservationStatus;
use App\Http\Controllers\ValidationRules\ToggleShiftReservationControllerRules;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class ToggleUserOntoShiftReservationController extends Controller
{
    public function __construct(
        private readonly ToggleShiftReservationControllerRules $toggleShiftReservationControllerRules,
        private readonly ToggleUserOntoShift $toggleUserOntoShift,
    ) {}

    /**
     * @throws ModelNotFoundException
     */
    public function __invoke(Request $request)
    {
        $request->validate([
            'user' => ['required', 'integer', 'exists:users,id'],
        ]);
        $user = User::findOrFail($request->integer('user'));

        $rules = $this->toggleShiftReservationControllerRules->execute($user, $request->all(), true);

        $data = $request->validate($rules);
        $status = $this->toggleUserOntoShift->execute($user, $data);

        return match ($status) {
            ToggleReservationStatus::RESERVATION_MADE => response('Reservation made', 200),
            ToggleReservationStatus::RESERVATION_REMOVED => response('Reservation removed', 200),
            ToggleReservationStatus::NO_AVAILABLE_SHIFTS => ErrorApiResource::create('Shift is at maximum capacity', ErrorApiResource::CODE_NO_AVAILABLE_SHIFTS, 422),
        };
    }
}
