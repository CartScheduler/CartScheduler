<?php

namespace App\Http\Controllers;

use App\Actions\GetOutstandingReports;
use App\Data\OutstandingReportsData;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MissingReportsForUserController extends Controller
{
    public function __invoke(Request $request, GetOutstandingReports $getOutstandingReports): Collection
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return $getOutstandingReports->execute($user)
            ->filter(function (OutstandingReportsData $report) use ($user) {
                if ($report->requires_brother) {
                    return $user->gender === 'male';
                }

                return true;
            });
    }
}
