<?php

namespace App\Http\Controllers;

use App\Data\IdAndNameData;
use App\Data\ReportsData;
use App\Models\Location;
use App\Models\Report;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Reports/List', [
            'locations' => IdAndNameData::collect(Location::get(['id', 'name'])),
            'reports'   => ReportsData::collect(
                Report::query()
                    ->with(['tags'])
                    ->orderBy('id', 'desc')
                    ->get()
            ),
        ]);
    }
}
