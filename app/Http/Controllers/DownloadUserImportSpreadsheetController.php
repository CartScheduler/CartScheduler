<?php

namespace App\Http\Controllers;

use App\Exports\UsersExport;
use App\Settings\GeneralSettings;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class DownloadUserImportSpreadsheetController extends Controller
{
    public function __construct(private readonly GeneralSettings $settings) {}

    public function __invoke()
    {
        $siteName = Str::snake($this->settings->siteName);

        return Excel::download((new UsersExport)->excludeData(), "$siteName-user_import_template.xlsx");
    }
}
