<?php

namespace App\Actions;

use App\Mail\ShiftAssignmentsReleased;
use App\Models\ShiftAssignmentNotification;
use App\Models\ShiftUser;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

readonly class NotifyVolunteersOfReleasedShifts
{
    public function __construct(private GetNewlyReleasedShiftDateRange $getNewlyReleasedShiftDateRange)
    {
    }

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
            ->with(['user', 'shift.location'])
            ->whereDoesntHave('assignmentNotification')
            ->whereDate('shift_date', '>=', $range['start']->toDateString())
            ->whereDate('shift_date', '<=', $range['end']->toDateString())
            ->whereHas('user', fn($query) => $query->enabled()->whereNotNull('email'))
            ->orderBy('shift_date')
            ->get();

        if ($assignments->isEmpty()) {
            return ['users_notified' => 0, 'assignments_notified' => 0];
        }

        $volunteersByShift = $this->volunteersGroupedByShift($assignments);

        $sentAt              = Carbon::now();
        $usersNotified       = 0;
        $assignmentsNotified = 0;

        $assignments->groupBy('user_id')->each(function (Collection $userAssignments) use ($volunteersByShift, $sentAt, &$usersNotified, &$assignmentsNotified) {
            /** @var User $user */
            $user = $userAssignments->first()->user;

            $shifts = $userAssignments->map(function (ShiftUser $assignment) use ($user, $volunteersByShift) {
                $shiftDate = Carbon::parse($assignment->shift_date)->toDateString();
                $key       = $assignment->shift_id . '|' . $shiftDate;

                $otherVolunteers = $volunteersByShift
                    ->get($key, collect())
                    ->reject(fn(ShiftUser $shiftUser) => $shiftUser->user_id === $user->id)
                    ->map(fn(ShiftUser $shiftUser) => $shiftUser->user)
                    ->filter()
                    ->sortBy('name')
                    ->values()
                    ->map(fn(User $volunteer) => [
                        'name'         => $volunteer->name,
                        'mobile_phone' => $volunteer->mobile_phone,
                    ])
                    ->all();

                return [
                    'date'             => Carbon::parse($assignment->shift_date)->format('l, j F Y'),
                    'start_time'       => Carbon::parse($assignment->shift->start_time)->format('g:i A'),
                    'end_time'         => Carbon::parse($assignment->shift->end_time)->format('g:i A'),
                    'location'         => $assignment->shift->location->name,
                    'other_volunteers' => $otherVolunteers,
                ];
            });

            Mail::to($user->email)->send(new ShiftAssignmentsReleased($user, $shifts));
            ShiftAssignmentNotification::insert(
                $userAssignments->map(fn(ShiftUser $assignment) => [
                    'shift_user_id' => $assignment->id,
                    'sent_at'       => $sentAt,
                ])->all()
            );

            $usersNotified++;
            $assignmentsNotified += $userAssignments->count();
        });

        return [
            'users_notified'       => $usersNotified,
            'assignments_notified' => $assignmentsNotified,
        ];
    }

    /**
     * @param  Collection<int, ShiftUser>  $assignments
     * @return Collection<string, Collection<int, ShiftUser>>
     */
    private function volunteersGroupedByShift(Collection $assignments): Collection
    {
        $pairs = $assignments
            ->map(fn(ShiftUser $assignment) => [
                'shift_id'   => $assignment->shift_id,
                'shift_date' => Carbon::parse($assignment->shift_date)->toDateString(),
            ])
            ->unique(fn(array $pair) => $pair['shift_id'] . '|' . $pair['shift_date'])
            ->values();

        return ShiftUser::query()
            ->with('user:id,name,mobile_phone')
            ->where(function ($query) use ($pairs) {
                foreach ($pairs as $pair) {
                    $query->orWhere(function ($shiftQuery) use ($pair) {
                        $shiftQuery
                            ->where('shift_id', $pair['shift_id'])
                            ->whereDate('shift_date', $pair['shift_date']);
                    });
                }
            })
            ->get()
            ->groupBy(fn(ShiftUser $shiftUser) => $shiftUser->shift_id . '|' . Carbon::parse($shiftUser->shift_date)->toDateString());
    }
}
