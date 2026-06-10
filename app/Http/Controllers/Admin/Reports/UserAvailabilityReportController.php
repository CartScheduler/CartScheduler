<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Actions\Admin\Reports\GetUserAvailabilityReportAction;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * WIP, ignore in coverage until it's done
 * @codeCoverageIgnore
 */
class UserAvailabilityReportController extends Controller
{
    public function __invoke(Request $request, GetUserAvailabilityReportAction $action): Response
    {
        // Validate date parameters if provided
        $validated = $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
        ]);

        // Get start date, defaulting to today if not provided
        $startDate = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])
            : Carbon::today();

        // Get end date, defaulting to today + 4 weeks if not provided
        $endDate = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])
            : Carbon::today()->addWeeks(4);

        // Execute the action with the date parameters
        $reportData = $action->execute($startDate, $endDate);

        return Inertia::render('Admin/Reporting/UserAvailabilityReport', [
            'data' => $reportData,
            'meta' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
        ]);
    }
}
