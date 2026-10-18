<?php

namespace App\Actions;

use App\Models\Location;
use App\Models\ShiftUser;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the shift list for a volunteer's "Shift Assignments Released" email from their assignment IDs.
 *
 * This runs when the email is sent rather than when it is queued, so names and phone numbers are never
 * stored in the jobs table and always reflect the current roster.
 */
readonly class BuildShiftAssignmentEmailShifts
{
    /**
     * @param  list<int>  $shiftUserIds
     * @return Collection<int, array{date: string, start_time: string, end_time: string, location: string, location_description: string, location_map_url: ?string, other_volunteers: list<array{name: string, mobile_phone: ?string}>}>
     */
    public function execute(User $user, array $shiftUserIds): Collection
    {
        $assignments = ShiftUser::query()
            ->with('shift.location')
            ->whereIn('id', $shiftUserIds)
            ->where('user_id', $user->id)
            ->whereHas('shift.location')
            ->orderBy('shift_date')
            ->get();

        if ($assignments->isEmpty()) {
            return collect();
        }

        $volunteersByShift = $this->volunteersGroupedByShift($assignments);

        return $assignments->map(function (ShiftUser $assignment) use ($user, $volunteersByShift) {
            $otherVolunteers = $volunteersByShift
                ->get($this->shiftKey($assignment), collect())
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
        })->values();
    }

    private function shiftKey(ShiftUser $shiftUser): string
    {
        return $shiftUser->shift_id.'|'.Carbon::parse($shiftUser->shift_date)->toDateString();
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
            ->groupBy(fn (ShiftUser $shiftUser) => $this->shiftKey($shiftUser));
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
