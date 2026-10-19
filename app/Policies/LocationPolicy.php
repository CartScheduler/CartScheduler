<?php

/**
 * Project: ${PROJECT_NAME}
 * Owner: Pixelated
 * Copyright: 2022
 */

namespace App\Policies;

use App\Enums\Role;
use App\Models\Location;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LocationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->role === Role::Admin->value;
    }

    public function view(User $user, Location $location): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === Role::Admin->value;
    }

    public function update(User $user, Location $location): bool
    {
        return $user->role === Role::Admin->value;
    }

    public function delete(User $user, Location $location): bool
    {
        return $user->role === Role::Admin->value;
    }

    public function restore(User $user, Location $location): bool
    {
        return $user->role === Role::Admin->value;
    }

    public function forceDelete(User $user, Location $location): bool
    {
        return $user->role === Role::Admin->value;
    }
}
