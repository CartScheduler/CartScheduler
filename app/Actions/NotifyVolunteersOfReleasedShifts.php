<?php

namespace App\Actions;

use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Mail\EmailPlaceholderRegistry;
use App\Models\ShiftUser;
use App\Models\User;
use App\Notifications\ShiftAssignmentsReleasedNotification;
use Illuminate\Support\Collection;

readonly class NotifyVolunteersOfReleasedShifts
{
    public function __construct(
        private GetNewlyReleasedShiftDateRange $getNewlyReleasedShiftDateRange,
        private EmailPlaceholderRegistry $placeholderRegistry,
    ) {}

    /**
     * @return array{users_notified: int, assignments_notified: int}
     */
    public function execute(bool $force = false): array
    {
        $range = $this->getNewlyReleasedShiftDateRange->execute($force);

        if ($range === null) {
            return ['users_notified' => 0, 'assignments_notified' => 0];
        }

        $assignments = ShiftUser::query()
            ->with('user')
            ->whereDoesntHave('notificationLogs', fn ($query) => $query
                ->where('type', NotificationType::ShiftAssignmentsReleased->value)
                ->whereIn('status', NotificationStatus::active()))
            ->whereDate('shift_date', '>=', $range['start']->toDateString())
            ->whereDate('shift_date', '<=', $range['end']->toDateString())
            ->whereHas('user', fn ($query) => $query->enabled()->whereNotNull('email'))
            ->orderBy('shift_date')
            ->get();

        if ($assignments->isEmpty()) {
            return ['users_notified' => 0, 'assignments_notified' => 0];
        }

        $period = $this->placeholderRegistry->periodVariables($range['start'], $range['end']);
        $usersNotified = 0;
        $assignmentsNotified = 0;

        $assignments->groupBy('user_id')->each(function (Collection $userAssignments) use ($period, &$usersNotified, &$assignmentsNotified) {
            /** @var User $user */
            $user = $userAssignments->first()->user;

            $user->notify(new ShiftAssignmentsReleasedNotification(
                $userAssignments->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                $period,
            ));

            $usersNotified++;
            $assignmentsNotified += $userAssignments->count();
        });

        return [
            'users_notified' => $usersNotified,
            'assignments_notified' => $assignmentsNotified,
        ];
    }
}
