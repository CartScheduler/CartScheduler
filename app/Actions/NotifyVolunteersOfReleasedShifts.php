<?php

namespace App\Actions;

use App\Jobs\SendShiftAssignmentsReleasedMail;
use App\Mail\EmailPlaceholderRegistry;
use App\Models\Location;
use App\Models\ShiftUser;
use App\Models\User;
use Illuminate\Support\Carbon;
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
            ->with(['user', 'shift.location'])
            ->whereDoesntHave('assignmentNotification')
            ->whereDate('shift_date', '>=', $range['start']->toDateString())
            ->whereDate('shift_date', '<=', $range['end']->toDateString())
            ->whereHas('user', fn ($query) => $query->enabled()->whereNotNull('email'))
            ->orderBy('shift_date')
            ->get();

        if ($assignments->isEmpty()) {
            return ['users_notified' => 0, 'assignments_notified' => 0];
        }

        $volunteersByShift = $this->volunteersGroupedByShift($assignments);
        $period = $this->placeholderRegistry->periodVariables($range['start'], $range['end']);

        $usersNotified = 0;
        $assignmentsNotified = 0;

        $assignments->groupBy('user_id')->each(function (Collection $userAssignments) use ($volunteersByShift, $period, &$usersNotified, &$assignmentsNotified) {
            /** @var User $user */
            $user = $userAssignments->first()->user;

            $shifts = $userAssignments->map(function (ShiftUser $assignment) use ($user, $volunteersByShift) {
                $shiftDate = Carbon::parse($assignment->shift_date)->toDateString();
                $key = $assignment->shift_id.'|'.$shiftDate;

                $otherVolunteers = $volunteersByShift
                    ->get($key, collect())
                    ->reject(fn (ShiftUser $shiftUser) => $shiftUser->user_id === $user->id)
                    ->map(fn (ShiftUser $shiftUser) => $shiftUser->user)
                    ->filter()
                    ->sortBy('name')
                    ->values()
                    ->map(fn (User $volunteer) => [
                        'name' => $volunteer->name,
                        'mobile_phone' => $volunteer->mobile_phone,
                    ])
                    ->all();

                $location = $assignment->shift->location;

                return [
                    'date' => Carbon::parse($assignment->shift_date)->format('l, j F Y'),
                    'start_time' => Carbon::parse($assignment->shift->start_time)->format('g:i A'),
                    'end_time' => Carbon::parse($assignment->shift->end_time)->format('g:i A'),
                    'location' => $location->name,
                    'location_description' => $this->plainLocationDescription($location->description),
                    'location_map_url' => $this->locationMapUrl($location),
                    'other_volunteers' => $otherVolunteers,
                ];
            });

            SendShiftAssignmentsReleasedMail::dispatch(
                $user,
                $shifts,
                $period,
                $userAssignments->pluck('id')->map(fn ($id) => (int) $id)->all(),
            );

            $usersNotified++;
            $assignmentsNotified += $userAssignments->count();
        });

        return [
            'users_notified' => $usersNotified,
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
            ->map(fn (ShiftUser $assignment) => [
                'shift_id' => $assignment->shift_id,
                'shift_date' => Carbon::parse($assignment->shift_date)->toDateString(),
            ])
            ->unique(fn (array $pair) => $pair['shift_id'].'|'.$pair['shift_date'])
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
            ->groupBy(fn (ShiftUser $shiftUser) => $shiftUser->shift_id.'|'.Carbon::parse($shiftUser->shift_date)->toDateString());
    }

    private function plainLocationDescription(?string $description): string
    {
        if ($description === null || $description === '') {
            return '';
        }

        $text = html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function locationMapUrl(Location $location): ?string
    {
        $latitude = $location->latitude;
        $longitude = $location->longitude;

        if ($latitude === null || $longitude === null || $latitude === '' || $longitude === '') {
            return null;
        }

        return 'https://www.google.com/maps?q='.$latitude.','.$longitude;
    }
}
