<?php

namespace App\Models\Traits\User;

use App\Enums\Role;
use App\Models\User;
use App\Settings\GeneralSettings;

trait RevokesUserAccess
{
    /**
     * If a user has been made 'restricted', this will automatically revoke any 'extended' privileges they may have had
     */
    public static function revokePrivilegedAccess(User $user): void
    {
        if ($user->is_unrestricted) {
            return;
        }
        self::deactivateSystemSettingsAccess($user);
        self::revokeAdminAccess($user);
    }

    protected static function deactivateSystemSettingsAccess(User $user): void
    {
        $settings = app()->make(GeneralSettings::class);
        if (in_array($user->id, $settings->allowedSettingsUsers, true)) {
            // remove the user->id from the allowedSettingsUsers array
            $settings->allowedSettingsUsers = array_diff($settings->allowedSettingsUsers, [$user->id]);
            $settings->save();
        }
    }

    protected static function revokeAdminAccess(User $user): void
    {
        if ($user->role === Role::Admin->value) {
            $user->role = Role::User->value;
            $user->save();
        }
    }
}
