<?php

namespace App\Http\Controllers;

use App\Actions\IsAdminForUpdateOfUserAction;
use App\Http\Requests\UserLocationChoicesRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UpdateUserLocationsChoicesController extends Controller
{
    public function __construct(private readonly IsAdminForUpdateOfUserAction $isAdminForUpdateOfUserAction) {}

    /**
     * @return RedirectResponse
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     * @throws HttpException
     * @throws NotFoundHttpException
     */
    public function __invoke(UserLocationChoicesRequest $request)
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorize('update', $user);

        [$isAdminEdit, $user] = $this->isAdminForUpdateOfUserAction->execute($request);

        $locationIds = $request->validated('selectedLocations', []);

        $user->rosterLocations()->sync($locationIds);

        session()->flash('flash.banner', $isAdminEdit ? 'volunteer preferred locations have been updated.' : 'your preferred locations have been updated.');
        session()->flash('flash.bannerStyle', 'success');

        if ($isAdminEdit) {
            return Redirect::route('admin.users.edit', $user);
        }

        return Redirect::route('user.availability');
    }
}
