<?php

namespace App\Models\Traits\User;

use App\Models\User;

trait AttachesSpouse
{
    public static function attachSpouse(User $user): void
    {
        if ($user->spouse_id) {
            $spouse = User::find($user->spouse_id);
            $spouse->spouse_id = $user->id;
            $spouse->marital_status = 'married';
            $spouse->saveQuietly();
        }
    }

    /**
     * Only a married user can have a spouse. Clearing `spouse_id` here, before the
     * `updating` event, lets `detachSpouse()` release the former spouse as well.
     */
    public static function clearSpouseUnlessMarried(User $user): void
    {
        if ($user->spouse_id !== null && $user->marital_status !== 'married') {
            $user->spouse_id = null;
        }
    }

    public static function detachSpouse(User $user): void
    {
        $originalSpouseId = $user->getOriginal('spouse_id');
        if (
            $user->isDirty('spouse_id') &&
            $originalSpouseId !== null &&
            (int) $user->spouse_id !== (int) $originalSpouseId
        ) {
            // Covers both removing the spouse and swapping to a different one
            $spouse = User::find($originalSpouseId);
            if ($spouse && (int) $spouse->spouse_id === (int) $user->id) {
                $spouse->spouse_id = null;
                $spouse->saveQuietly();
            }
        }
    }
}
