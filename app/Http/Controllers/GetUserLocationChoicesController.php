<?php

namespace App\Http\Controllers;

use App\Data\LocationChoiceData;
use App\Models\Location;
use App\Settings\GeneralSettings;
use Illuminate\Validation\ValidationException;

class GetUserLocationChoicesController extends Controller
{
    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function __invoke()
    {
        $settings = app()->make(GeneralSettings::class);
        if (!$settings->enableUserLocationChoices) {
            throw ValidationException::withMessages(['featureDisabled' => 'User location choices are not enabled.']);
        }

        $locations = Location::where('is_enabled', true)->orderBy('name')->get();
        return LocationChoiceData::collect($locations);
    }
}
