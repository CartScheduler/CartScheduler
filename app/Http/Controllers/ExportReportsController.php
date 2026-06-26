<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportDateRangeRequest;
use App\Http\Resources\ExportReportsDataResource;
use App\Http\Responses\ExportResponseFormatter;
use App\Models\Report;
use App\Settings\GeneralSettings;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ExportReportsController extends Controller
{
    public function __construct(private readonly GeneralSettings $settings) {}

    public function __invoke(ExportDateRangeRequest $request): Response
    {
        $startDate = $request->validated('start_date');
        $endDate = $request->validated('end_date');

        $resource = ExportReportsDataResource::collection(
            Report::query()
                ->with(['tags'])
                ->whereBetween('shift_date', [$startDate, $endDate])
                ->orderBy('shift_date')
                ->orderBy('id')
                ->get()
        );

        return ExportResponseFormatter::download(
            $request,
            $resource,
            $this->filename('reports'),
        );
    }

    private function filename(string $type): string
    {
        $dateTime = now()->format('Y-m-d_His');
        $siteName = Str::snake($this->settings->siteName);

        return "{$siteName}-{$type}_{$dateTime}.csv";
    }
}
