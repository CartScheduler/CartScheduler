<?php

namespace App\Http\Controllers;

use App\Actions\GetAvailableShiftsCount;
use App\Actions\GetLocationsForShifts;
use App\Data\LocationData;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminAvailableShiftsController extends Controller
{
    public function __construct(
        private readonly GetLocationsForShifts $getLocationsForShifts,
        private readonly GetAvailableShiftsCount $getAvailableShiftsCount,
    ) {
    }

    public function __invoke(Request $request, string $shiftDate)
    {
        $returnData = [
            'freeShifts' => [],
            'locations'  => [],
        ];

        try {
            $selectedDate = Carbon::parse($shiftDate)->endOfDay();
        } catch (InvalidFormatException $e) {
            Bugsnag::notifyException($e);
            return $returnData;
        }

        $locations = $this->getLocationsForShifts->execute($selectedDate->toDateString());

        $selectedDate->startOfMonth();
        $endDate = $selectedDate->clone()->endOfMonth();

        $freeShiftsCount = $this->getAvailableShiftsCount->execute(
            startDate: $selectedDate->format('Y-m-d'),
            endDate: $endDate->format('Y-m-d'),
        );

        $returnData['freeShifts'] = $freeShiftsCount;
        $returnData['locations']  = LocationData::collect($locations);

        return $returnData;
    }
}
