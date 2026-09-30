<?php

namespace App\Jobs;

use App\Mail\ShiftAssignmentsReleased;
use App\Models\ShiftAssignmentNotification;
use App\Models\ShiftUser;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class SendShiftAssignmentsReleasedMail implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    /**
     * @param  Collection<int, array{date: string, start_time: string, end_time: string, location: string, location_description?: string|null, location_map_url?: string|null, other_volunteers: list<array{name: string, mobile_phone: ?string}>}>  $shifts
     * @param  array{start: string, end: string, label: string}  $period
     * @param  list<int>  $shiftUserIds
     */
    public function __construct(
        public User $user,
        public Collection $shifts,
        public array $period,
        public array $shiftUserIds,
    ) {}

    public function backoff(): array
    {
        return [30, 60, 120, 240];
    }

    public function uniqueId(): string
    {
        $ids = $this->shiftUserIds;
        sort($ids);

        return 'shift-assignments-released:'.implode(',', $ids);
    }

    public function handle(): void
    {
        $pendingIds = ShiftUser::query()
            ->whereIn('id', $this->shiftUserIds)
            ->whereDoesntHave('assignmentNotification')
            ->pluck('id');

        if ($pendingIds->isEmpty() || $this->user->email === null) {
            return;
        }

        Mail::to($this->user->email)->send(new ShiftAssignmentsReleased($this->user, $this->shifts, $this->period));

        $sentAt = Carbon::now();

        ShiftAssignmentNotification::insert(
            $pendingIds->map(fn ($id) => [
                'shift_user_id' => $id,
                'sent_at' => $sentAt,
            ])->all()
        );
    }
}
