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

    public function __construct(public User $user) {}

    public function build(): static
    {
        $token = Password::createToken($this->user);

        $rendered = app(EmailTemplateRenderer::class)->renderForMailable('user-account-created', [
            'user' => $this->user,
            'token' => $token,
        ]);

        if ($rendered['view'] === 'emails.dynamic-template') {
            return $this->subject($rendered['subject'])
                ->markdown($rendered['view'], ['body' => $rendered['body']]);
        }

        $this->subject = $rendered['subject'];

        return $this->markdown($rendered['view'])->with('token', $token);
    }
}
