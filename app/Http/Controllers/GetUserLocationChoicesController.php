<?php

namespace App\Http\Controllers;

use App\Data\LocationChoiceData;
use App\Models\Location;
use App\Settings\GeneralSettings;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Validation\ValidationException;

class GetUserLocationChoicesController extends Controller
{
    /**
     * @throws BindingResolutionException
     * @throws ValidationException
     */
    public function __invoke()
    {
        $settings = app()->make(GeneralSettings::class);
        if (! $settings->enableUserLocationChoices) {
            throw ValidationException::withMessages(['featureDisabled' => 'User location choices are not enabled.']);
        }

        $locations = Location::where('is_enabled', true)->orderBy('name')->get();

        return LocationChoiceData::collect($locations);
    }
}
