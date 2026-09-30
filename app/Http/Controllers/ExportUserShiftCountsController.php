<?php

namespace App\Http\Controllers;

use App\Data\ExportUserShiftCountData;
use App\Http\Requests\ExportDateRangeRequest;
use App\Http\Responses\ExportResponseFormatter;
use App\Models\ShiftUser;
use Symfony\Component\HttpFoundation\Response;

class ExportUserShiftCountsController extends Controller
{
    public function __construct(private readonly ExportResponseFormatter $formatter) {}

    public function __invoke(ExportDateRangeRequest $request): Response
    {
        $startDate = $request->validated('start_date');
        $endDate = $request->validated('end_date');

        $rows = ExportUserShiftCountData::collect(
            ShiftUser::query()
                ->join('users', 'users.id', '=', 'shift_user.user_id')
                ->whereBetween('shift_user.shift_date', [$startDate, $endDate])
                ->groupBy('users.id', 'users.name', 'users.email')
                ->orderBy('users.name')
                ->select([
                    'users.id',
                    'users.name',
                    'users.email',
                ])
                ->selectRaw('COUNT(*) as shift_count')
                ->get()
        );

        return $this->formatter->download($rows, 'shift-counts');
    }
}
