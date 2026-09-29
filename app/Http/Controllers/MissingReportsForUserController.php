<?php

namespace App\Http\Controllers;

use App\Actions\GetOutstandingReports;
use App\Data\OutstandingReportsData;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MissingReportsForUserController extends Controller
{
    public function __invoke(Request $request, GetOutstandingReports $getOutstandingReports): Collection
    {
        /** @var User $user */
        $user = $request->user();

        return $getOutstandingReports->execute($user)
            ->filter(function (OutstandingReportsData $report) use ($user) {
                if ($report->requires_brother) {
                    return $user->gender === 'male';
                }

                return true;
            })
            // filter() keeps the original keys, and a collection with gaps in
            // its keys encodes as a JSON object rather than an array. The
            // client reads `.length` off the response, so the gaps left by a
            // sister's brother-only shifts would hide every report she does owe.
            ->values();
    }
}
