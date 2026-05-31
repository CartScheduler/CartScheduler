<?php

namespace App\Http\Controllers;

use App\Http\Resources\ExportUserAvailabilityResource;
use App\Http\Responses\ExportResponseFormatter;
use App\Models\UserAvailability;
use App\Settings\GeneralSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ExportUserAvailabilitiesController extends Controller
{
    public function __construct(private readonly GeneralSettings $settings) {}

    public function __invoke(Request $request): Response
    {
        $resource = ExportUserAvailabilityResource::collection(
            UserAvailability::query()
                ->select('user_availabilities.*')
                ->join('users', 'users.id', '=', 'user_availabilities.user_id')
                ->with('user:id,name')
                ->orderBy('users.name')
                ->get()
        );

        return ExportResponseFormatter::download(
            $request,
            $resource,
            $this->filename('user-availabilities'),
        );
    }

    private function filename(string $type): string
    {
        $dateTime = now()->format('Y-m-d_His');
        $siteName = Str::snake($this->settings->siteName);

        return "{$siteName}-{$type}_{$dateTime}.csv";
    }
}
