<?php

namespace App\Models\Traits\User;

use App\Models\User;

trait AttachesSpouse
{
    public static function attachSpouse(User $user): void
    {
        if ($user->spouse_id) {
            $spouse                 = User::find($user->spouse_id);
            $spouse->spouse_id      = $user->id;
            $spouse->marital_status = 'married';
            $spouse->saveQuietly();
        }
    }

    public static function detachSpouse(User $user): void
    {
        $dirty    = $user->getDirty();
        $original = $user->getOriginal();
        if (
            array_key_exists('spouse_id', $dirty) &&
            $dirty['spouse_id'] === null &&
            $original['spouse_id'] !== null
        ) {
            $spouse            = User::find($original['spouse_id']);
            $spouse->spouse_id = null;
            $spouse->saveQuietly();
        }
    }
}
