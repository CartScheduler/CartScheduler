<?php

namespace App\Http\Controllers;

use App\Actions\IsAdminForUpdateOfUserAction;
use App\Http\Requests\UserAvailabilityRequest;
use App\Models\User;
use Illuminate\Support\Facades\Redirect;

class UpdateUserRegularAvailabilityController extends Controller
{
    public function __construct(private readonly IsAdminForUpdateOfUserAction $isAdminForUpdateOfUserAction)
    {
    }

    /**
     * @param \App\Http\Requests\UserAvailabilityRequest $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function __invoke(UserAvailabilityRequest $request)
    {
        [$isAdminEdit, $user] = $this->isAdminForUpdateOfUserAction->execute($request);

        if ($request->validated('user_id')) {
            $user = User::findOrFail($request->validated('user_id'));
        }

        $user->load('availability');
        $availability = $user->availability ?? $user->availability()->create();

        $availability->num_mondays    = $request->validated('num_mondays');
        $availability->num_tuesdays   = $request->validated('num_tuesdays');
        $availability->num_wednesdays = $request->validated('num_wednesdays');
        $availability->num_thursdays  = $request->validated('num_thursdays');
        $availability->num_fridays    = $request->validated('num_fridays');
        $availability->num_saturdays  = $request->validated('num_saturdays');
        $availability->num_sundays    = $request->validated('num_sundays');

        $availability->day_monday    = $request->validated('day_monday');
        $availability->day_tuesday   = $request->validated('day_tuesday');
        $availability->day_wednesday = $request->validated('day_wednesday');
        $availability->day_thursday  = $request->validated('day_thursday');
        $availability->day_friday    = $request->validated('day_friday');
        $availability->day_saturday  = $request->validated('day_saturday');
        $availability->day_sunday    = $request->validated('day_sunday');

        $availability->comments = $request->validated('comments');

        $user->availability = $availability;
        $user->availability->save();

        session()->flash('flash.banner', $isAdminEdit ? 'Volunteer availability has been updated.' : 'Your availability has been updated.');
        session()->flash('flash.bannerStyle', 'success');

        if ($isAdminEdit) {
            return Redirect::route('admin.users.edit', $user);
        }
        return Redirect::route('user.availability');
    }
}
