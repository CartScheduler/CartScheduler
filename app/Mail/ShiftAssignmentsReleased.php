<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ShiftAssignmentsReleased extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, array{date: string, start_time: string, end_time: string, location: string, other_volunteers: list<array{name: string, mobile_phone: ?string}>}>  $shifts
     */
    public function __construct(public User $user, public Collection $shifts)
    {
        $this->subject = config('app.name') . ' Shift Assignments';
    }

    public function build(): static
    {
        return $this->markdown('emails.shift-assignments-released');
    }
}
