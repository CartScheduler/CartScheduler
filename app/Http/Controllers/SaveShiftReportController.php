<?php

namespace App\Http\Controllers;

use App\Actions\GetPrefixedUserName;
use App\Http\Requests\CreateShiftReportRequest;
use App\Models\Report;
use App\Models\ShiftUser;
use App\Models\User;
use Illuminate\Support\Collection;

class SaveShiftReportController extends Controller
{
    public function __construct(private readonly GetPrefixedUserName $getPrefixedUserName)
    {
    }

    public function __invoke(CreateShiftReportRequest $request)
    {
        $shiftUser = ShiftUser::with('shift.location')
            ->where('shift_id', $request->get('shift_id'))
            ->where('shift_date', $request->get('shift_date'))
            ->first();
        if (!$shiftUser || $shiftUser->shift->start_time !== $request->get('start_time')) {
            return response()->json(
                ['message' => 'The supplied data does not match a shift. Please contact the administrator and cite this message'],
                422,
            );
        }

        /** @var \App\Models\User $user */
        $user = $request->user();
        // Find all other users who were on the same shift
        $associates = ShiftUser::with('user')
            ->where('shift_id', $shiftUser->shift->id)
            ->where('shift_date', $shiftUser->shift_date)
            ->where('user_id', '!=', $user->id)
            ->get()
            ->pluck('user');

        if ($shiftUser->shift->location->requires_brother && $request->user()->gender === 'female') {
            return response()->json(['message' => 'A brother is required to report on this shift.'], 422);
        }

        $cancelled = $request->get('shift_was_cancelled', false);

        $report                           = new Report();
        $report->report_submitted_user_id = $user->id;
        $report->shift_id                 = $shiftUser->shift->id;
        $report->shift_date               = $shiftUser->shift_date;
        $report->shift_was_cancelled      = $cancelled;
        $report->placements_count         = $cancelled ? 0 : $request->get('placements_count', 0);
        $report->videos_count             = $cancelled ? 0 : $request->get('videos_count', 0);
        $report->requests_count           = $cancelled ? 0 : $request->get('requests_count', 0);
        $report->comments                 = $request->get('comments');
        $report->metadata                 = $this->prepareMetadata($shiftUser, $user, $associates);
        $report->save();

        $report->tags()->sync($request->get('tags', []));

        return response()->json(['message' => 'Report saved successfully']);
    }

    private function prepareMetadata(ShiftUser $shiftUser, User $user, Collection $associates): array
    {
        // TODO: This needs to be a DTO
        $metadata                       = [];
        $metadata['shift_id']           = $shiftUser->shift->id;
        $metadata['shift_time']         = $shiftUser->shift->start_time;
        $metadata['location_id']        = $shiftUser->shift->location->id;
        $metadata['location_name']      = $shiftUser->shift->location->name;
        $metadata['submitted_by_id']    = $user->id;
        $metadata['submitted_by_name']  = $this->getPrefixedUserName->execute($user);
        $metadata['submitted_by_email'] = $user->email;
        $metadata['submitted_by_phone'] = $user->mobile_phone;
        $metadata['associates']         = $associates->map(fn(User $associate) => [
            'id'   => $associate->id,
            'name' => $this->getPrefixedUserName->execute($associate),
        ])->toArray();
        return $metadata;
    }
}
