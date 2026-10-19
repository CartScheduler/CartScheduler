<?php

namespace App\Http\Controllers;

use App\Actions\ErrorApiResource;
use App\Actions\ToggleUserOntoShift;
use App\Enums\ToggleReservationStatus;
use App\Http\Controllers\ValidationRules\ToggleShiftReservationControllerRules;
use Illuminate\Http\Request;

class ToggleShiftReservationController extends Controller
{
    public function __construct(
        private readonly ToggleShiftReservationControllerRules $toggleShiftReservationControllerRules,
        private readonly ToggleUserOntoShift $toggleUserOntoShift,
    ) {}

    public function __invoke(Request $request)
    {
        $user = $request->user();
        if ($user->is_restricted) {
            return ErrorApiResource::create('You do not have permission to do this', ErrorApiResource::CODE_NOT_ALLOWED, 422);
        }

        $data = $request->validate($this->toggleShiftReservationControllerRules->execute($user, $request->all()));

        $status = $this->toggleUserOntoShift->execute($user, $data);

        return match ($status) {
            ToggleReservationStatus::RESERVATION_MADE => response('Reservation made', 200),
            ToggleReservationStatus::RESERVATION_REMOVED => response('Reservation removed', 200),
            ToggleReservationStatus::NO_AVAILABLE_SHIFTS => ErrorApiResource::create('Shift is at maximum capacity', ErrorApiResource::CODE_NO_AVAILABLE_SHIFTS, 422),
        };
    }
}
