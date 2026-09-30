<?php

namespace App\Http\Controllers;

use App\Data\ExportShiftAssignmentData;
use App\Http\Requests\ExportDateRangeRequest;
use App\Http\Responses\ExportResponseFormatter;
use App\Models\ShiftUser;
use Symfony\Component\HttpFoundation\Response;

class ExportShiftAssignmentsController extends Controller
{
    public function __construct(private readonly ExportResponseFormatter $formatter) {}

    public function __invoke(ExportDateRangeRequest $request): Response
    {
        $startDate = $request->validated('start_date');
        $endDate = $request->validated('end_date');

        $rows = ExportShiftAssignmentData::collect(
            ShiftUser::query()
                ->select('shift_user.*')
                ->join('users', 'users.id', '=', 'shift_user.user_id')
                ->with(['user', 'shift.location'])
                ->whereBetween('shift_user.shift_date', [$startDate, $endDate])
                ->orderBy('shift_user.shift_date')
                ->orderBy('users.name')
                ->get()
        );

        return $this->formatter->download($rows, 'shift-assignments');
    }
}
