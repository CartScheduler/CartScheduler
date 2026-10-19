<?php

namespace App\Notifications;

use App\Mail\UserAccountCreated;
use App\Models\User;
use App\Notifications\Concerns\QueuesWithRetries;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * The welcome email with the set-password link. The link's token is created when the email is
 * sent, so time spent waiting in the queue does not shorten how long the link works.
 */
class UserAccountCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable, QueuesWithRetries;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): UserAccountCreated
    {
        return (new UserAccountCreated($notifiable))->to($notifiable->email);
    }
}
