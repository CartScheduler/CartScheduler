<?php

namespace App\Models\Traits\User;

use App\Models\User;
use App\Notifications\UserAccountCreatedNotification;

trait SendsWelcomeEmail
{
    public static function sendWelcomeEmail(User $user): void
    {
        $user->notify(new UserAccountCreatedNotification);
    }
}
