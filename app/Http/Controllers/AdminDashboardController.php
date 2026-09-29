<?php

namespace App\Http\Controllers;

use App\Actions\GetOutstandingReportCount;
use App\Actions\GetShiftFilledData;
use App\Data\FilledShiftData;
use App\Enums\CacheKey;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class AdminDashboardController extends Controller
{
    public function __invoke(GetShiftFilledData $shiftFilledData, GetOutstandingReportCount $getOutstandingReportCount)
    {
        return Inertia::render('Admin/Dashboard', [
            'totalUsers' => Cache::flexible(
                key: CacheKey::TotalUsers,
                ttl: [7200, 10800],
                callback: static fn () => User::all()->count()
            ),
            'totalLocations' => Cache::flexible(
                key: CacheKey::TotalLocations,
                ttl: [60, 300],
                callback: static fn () => Location::all()->count()
            ),
            'shiftFilledData' => Cache::flexible(
                key: CacheKey::ShiftFilledData,
                ttl: [7200, 10800],
                callback: static fn () => FilledShiftData::collect($shiftFilledData->execute('fortnight'))
            ),
            'outstandingReports' => Cache::flexible(
                key: CacheKey::OutstandingReports,
                ttl: [7200, 10800],
                callback: static fn () => $getOutstandingReportCount->execute()
            ),
        ]);
    }
}
