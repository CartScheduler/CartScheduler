<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresAllowedSettingsUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeleteFailedJobController extends Controller
{
    use EnsuresAllowedSettingsUser;

    public function __invoke(Request $request, string $uuid): RedirectResponse
    {
        $this->ensureAllowedSettingsUser($request);

        $deleted = DB::table(config('queue.failed.table', 'failed_jobs'))->where('uuid', $uuid)->delete();
        abort_if($deleted === 0, 404);

        session()->flash('flash.banner', 'The failed job was deleted.');
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }
}
