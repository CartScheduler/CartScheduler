<?php

namespace App\Http\Controllers;

use App\Exports\UsersExport;
use App\Settings\GeneralSettings;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class DownloadUsersAsSpreadsheetController extends Controller
{
    public function __construct(private readonly GeneralSettings $settings) {}

    public function __invoke()
    {
        $dateTime = now()->format('Y-m-d_His');
        $siteName = Str::snake($this->settings->siteName);

        return Excel::download((new UsersExport), "{$siteName}-user_dump_$dateTime.xlsx");
    }
}
