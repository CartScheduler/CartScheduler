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
     * @param  Collection<int, array{date: string, start_time: string, end_time: string, location: string, location_description?: string|null, location_map_url?: string|null, other_volunteers: list<array{name: string, mobile_phone: ?string}>}>  $shifts
     * @param  array{start: string, end: string, label: string}  $period
     */
    public function __construct(public User $user, public Collection $shifts, public array $period = []) {}

    public function build(): static
    {
        $rendered = app(EmailTemplateRenderer::class)->renderForMailable('shift-assignments-released', [
            'user' => $this->user,
            'shifts' => $this->shifts,
            'period' => $this->period,
        ]);

        if ($rendered['view'] === 'emails.dynamic-template') {
            return $this->subject($rendered['subject'])
                ->markdown($rendered['view'], ['body' => $rendered['body']]);
        }

        $this->subject = $rendered['subject'];

        return $this->markdown($rendered['view']);
    }
}
