<?php

namespace App\Http\Controllers;

use App\Data\ExportReportData;
use App\Http\Requests\ExportDateRangeRequest;
use App\Http\Responses\ExportResponseFormatter;
use App\Models\Report;
use Symfony\Component\HttpFoundation\Response;

class ExportReportsController extends Controller
{
    public function __construct(private readonly ExportResponseFormatter $formatter) {}

    public function __invoke(ExportDateRangeRequest $request): Response
    {
        $startDate = $request->validated('start_date');
        $endDate = $request->validated('end_date');

        $rows = ExportReportData::collect(
            Report::query()
                ->with(['tags'])
                ->whereBetween('shift_date', [$startDate, $endDate])
                ->orderBy('shift_date')
                ->orderBy('id')
                ->get()
        );

        return $this->formatter->download($rows, 'reports');
    }
}
