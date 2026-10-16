<?php

namespace App\Http\Controllers;

use App\Data\AvailabilityData;
use App\Data\UserVacationData;
use App\Models\User;
use App\Models\UserAvailability;
use App\Settings\GeneralSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ShowUserAvailabilityController extends Controller
{
    /**
     * @throws BindingResolutionException
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(Request $request)
    {
        $settings = app()->make(GeneralSettings::class);
        if (! $settings->enableUserAvailability) {
            throw ValidationException::withMessages(['featureDisabled' => 'Choosing availability is not enabled.']);
        }

        /** @var User $user */
        $user = $request->user();
        $this->authorize('view', $user);

        $availability = UserAvailability::firstOrCreate(['user_id' => $user->id])->refresh(); // refresh(), otherwise, we get incomplete data

        return Inertia::render('Profile/ShowAvailability', [
            'vacations' => UserVacationData::collect($user->vacations->all()),
            'availability' => AvailabilityData::from($availability),
            'selectedLocations' => $user->rosterLocations->pluck('id'),
        ]);
    }
}
