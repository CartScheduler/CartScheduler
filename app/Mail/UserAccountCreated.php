<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Password;

class UserAccountCreated extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user)
    {
        $this->subject = config('app.name') . ' Account Activation';
    }

    public function build(): static
    {
        $token = Password::createToken($this->user);

        return $this->markdown('emails.user-account-created')->with('token', $token);
    }
}
