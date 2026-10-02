<?php

namespace App\Http\Controllers\Concerns;

use App\Settings\GeneralSettings;
use Illuminate\Http\Request;

trait EnsuresAllowedSettingsUser
{
    protected function ensureAllowedSettingsUser(Request $request): void
    {
        $settings = app(GeneralSettings::class);

        if (! in_array($request->user()->getKey(), $settings->allowedSettingsUsers, true)) {
            abort(404);
        }
    }
}
