<?php

namespace App\Http\Controllers;

use App\Data\ExportUserAvailabilityData;
use App\Http\Responses\ExportResponseFormatter;
use App\Models\UserAvailability;
use Symfony\Component\HttpFoundation\Response;

class ExportUserAvailabilitiesController extends Controller
{
    public function __construct(private readonly ExportResponseFormatter $formatter) {}

    public function __invoke(): Response
    {
        $rows = ExportUserAvailabilityData::collect(
            UserAvailability::query()
                ->select('user_availabilities.*')
                ->join('users', 'users.id', '=', 'user_availabilities.user_id')
                ->with('user:id,name')
                ->orderBy('users.name')
                ->get()
        );

        return $this->formatter->download($rows, 'user-availabilities');
    }
}
