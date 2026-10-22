<?php

namespace App\Notifications;

use App\Actions\BuildShiftAssignmentEmailShifts;
use App\Enums\NotificationStatus;
use App\Mail\ShiftAssignmentsReleased;
use App\Models\ShiftUser;
use App\Models\User;
use App\Notifications\Concerns\QueuesWithRetries;
use App\Notifications\Contracts\HasLogSubjects;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class ShiftAssignmentsReleasedNotification extends Notification implements HasLogSubjects, ShouldQueue
{
    use Queueable, QueuesWithRetries;

    /**
     * Only IDs and the period dates are stored in the queue. Names and phone numbers are looked up
     * when the email is sent.
     *
     * @param  list<int>  $shiftUserIds
     * @param  array{start: string, end: string, label: string}  $period
     */
    public function __construct(public array $shiftUserIds, public array $period) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Skips the email when every assignment was removed while it waited in the queue.
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        if (ShiftUser::query()->whereIn('id', $this->shiftUserIds)->exists()) {
            return true;
        }

        app(NotificationLogger::class)->finish($this, $channel, NotificationStatus::Skipped,
            'None of the shift assignments exist any more.');

        return false;
    }

    public function toMail(User $notifiable): ShiftAssignmentsReleased
    {
        $shifts = app(BuildShiftAssignmentEmailShifts::class)->execute($notifiable, $this->shiftUserIds);

        return (new ShiftAssignmentsReleased($notifiable, $shifts, $this->period))->to($notifiable->email);
    }

    public function logSubjects(): Collection
    {
        return ShiftUser::query()->whereIn('id', $this->shiftUserIds)->get();
    }
}
