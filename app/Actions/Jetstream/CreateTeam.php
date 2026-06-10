<?php

namespace App\Actions\Jetstream;

use App\Models\Team;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Laravel\Jetstream\Contracts\CreatesTeams;
use Laravel\Jetstream\Events\AddingTeam;
use Laravel\Jetstream\Jetstream;

/**
 * Not used for now
 * @codeCoverageIgnore
 */
class CreateTeam implements CreatesTeams
{
    /**
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function create(mixed $user, array $input): Team
    {
        Gate::forUser($user)->authorize('create', Jetstream::newTeamModel());

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
        ])->validateWithBag('createTeam');

        AddingTeam::dispatch($user);

        $user->switchTeam($team = $user->ownedTeams()->create([
            'name' => $input['name'],
            'personal_team' => false,
        ]));

        return $team;
    }
}
