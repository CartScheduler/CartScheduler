<?php

namespace App\Actions;

use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Collection;

class ValidateVolunteerIsAllowedToBeRosteredAction
{
    public function execute(Location $location, User $user, Collection $currentVolunteers): true | string
    {
        if ($location->requires_brother && $user->gender === 'female') {
            $hasBro = $currentVolunteers->contains(fn(User $existingVolunteer) => $existingVolunteer->gender === 'male');

            if (!$hasBro && $currentVolunteers->count() >= $location->max_volunteers - 1) {
                return 'Sorry, the last volunteer for this shift needs to be a brother';
            }
        }
        return true;
    }
}
