<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportDateRangeRequest;
use App\Http\Resources\ExportUserShiftCountResource;
use App\Http\Responses\ExportResponseFormatter;
use App\Models\ShiftUser;
use App\Settings\GeneralSettings;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ExportUserShiftCountsController extends Controller
{
    public function __construct(private readonly GeneralSettings $settings) {}

    public function __invoke(ExportDateRangeRequest $request): Response
    {
        $startDate = $request->validated('start_date');
        $endDate = $request->validated('end_date');

        $resource = ExportUserShiftCountResource::collection(
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

        return ExportResponseFormatter::download(
            $request,
            $resource,
            $this->filename('shift-counts'),
        );
    }

    private function filename(string $type): string
    {
        $dateTime = now()->format('Y-m-d_His');
        $siteName = Str::snake($this->settings->siteName);

        return "{$siteName}-{$type}_{$dateTime}.csv";
    }
}
