<?php

namespace App\Models\Traits\User;

use App\Mail\UserAccountCreated;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

trait SendsWelcomeEmail {
    public static function sendWelcomeEmail(User $user): void
    {
        Mail::to($user->email)->send(new UserAccountCreated($user));
    }
}
