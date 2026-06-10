<?php

namespace App\Http\Controllers;

use App\Actions\GetAvailableShiftsCount;
use App\Actions\GetLocationsForShifts;
use App\Actions\GetMaxShiftReservationDateAllowed;
use App\Actions\GetUserShiftsData;
use App\Data\AvailableShiftsData;
use App\Data\LocationData;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\Request;

class AvailableShiftsController extends Controller
{
    public function __construct(
        private readonly GetLocationsForShifts $getLocationsForShifts,
        private readonly GetUserShiftsData $getUserShiftsData,
        private readonly GetAvailableShiftsCount $getAvailableShiftsCount,
        private readonly GetMaxShiftReservationDateAllowed $getMaxShiftReservationDateAllowed
    ) {
    }

    public function __invoke(Request $request, string $shiftDate)
    {
        $user             = $request->user();
        $endDate          = $this->getMaxShiftReservationDateAllowed->execute()->endOfDay();
        $formattedEndDate = $endDate->toDateString();

        $returnData = [
            'locations'          => [],
            'shifts'             => [],
            'freeShifts'         => [],
            'maxDateReservation' => $formattedEndDate,
        ];

        try {
            $selectedDate = Carbon::parse($shiftDate)->endOfDay();
            if ($selectedDate->isAfter($endDate)) {
                return $returnData;
            }
        } catch (InvalidFormatException $e) {
            Bugsnag::notifyException($e);
            return $returnData;
        }

        // Locations are the list of locations in the 'accordion' menu
        $locations = $this->getLocationsForShifts->execute(
            date: $selectedDate->toDateString(),
            user: $user,
            userFields: [
                'uuid',
                'name',
                'gender',
                'mobile_phone',
            ],
        );

        $formattedStartDate = Carbon::today()->endOfDay()->format('Y-m-d');
        $shifts             = $this->getUserShiftsData->execute($formattedStartDate, $formattedEndDate, $user);
        $freeShiftsCount    = $user->is_unrestricted
            ? $this->getAvailableShiftsCount->execute($formattedStartDate, $formattedEndDate)
            : [];

        return AvailableShiftsData::from([
            'locations'          => LocationData::collect($locations),
            'shifts'             => $shifts,
            'freeShifts'         => $freeShiftsCount,
            'maxDateReservation' => $formattedEndDate,
        ]);
    }
}
