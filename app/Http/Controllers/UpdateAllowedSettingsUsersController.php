<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAllowedSettingsUsersRequest;
use App\Settings\GeneralSettings;

class UpdateAllowedSettingsUsersController extends Controller
{
    public function __construct(private readonly GeneralSettings $settings) {}

    public function __invoke(UpdateAllowedSettingsUsersRequest $request)
    {
        $this->settings->allowedSettingsUsers = $request->input('allowedSettingsUsers');
        $this->settings->save();

        return to_route('admin.settings');
    }
}
