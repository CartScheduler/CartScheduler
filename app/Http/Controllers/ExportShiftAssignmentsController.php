<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportDateRangeRequest;
use App\Http\Resources\ExportShiftAssignmentResource;
use App\Http\Responses\ExportResponseFormatter;
use App\Models\ShiftUser;
use App\Settings\GeneralSettings;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ExportShiftAssignmentsController extends Controller
{
    public function __construct(private readonly GeneralSettings $settings) {}

    public function __invoke(ExportDateRangeRequest $request): Response
    {
        $startDate = $request->validated('start_date');
        $endDate = $request->validated('end_date');

        $resource = ExportShiftAssignmentResource::collection(
            ShiftUser::query()
                ->select('shift_user.*')
                ->join('users', 'users.id', '=', 'shift_user.user_id')
                ->with(['user', 'shift.location'])
                ->whereBetween('shift_user.shift_date', [$startDate, $endDate])
                ->orderBy('shift_user.shift_date')
                ->orderBy('users.name')
                ->get()
        );

        return ExportResponseFormatter::download(
            $request,
            $resource,
            $this->filename('shift-assignments'),
        );
    }

    private function filename(string $type): string
    {
        $dateTime = now()->format('Y-m-d_His');
        $siteName = Str::snake($this->settings->siteName);

        return "{$siteName}-{$type}_{$dateTime}.csv";
    }
}
