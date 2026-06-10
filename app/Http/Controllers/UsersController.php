<?php

namespace App\Http\Controllers;

use App\Data\UserAdminData;
use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Models\UserAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UsersController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(User::class, 'user');
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Users/List', [
            'users' => UserAdminData::collect(User::with('spouse')->get()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Add');
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data             = $request->validated();
        $data['password'] = null; // Set it to null. Once set, the user will be unable to log in
        $data['uuid']     = Str::uuid();

        // The user model will automatically send a welcome email via the created event
        $user = User::unguarded(static fn() => User::create($data));

        session()?->flash('flash.message', "$user->name was successfully created.");
        return Redirect::route('admin.users.edit', $user);
    }

    public function edit(User $user): Response
    {
        UserAvailability::where('user_id', $user->id)
            ->firstOr(fn() => UserAvailability::create(['user_id' => $user->id]));
        return Inertia::render('Admin/Users/Edit', [
            'editUser' => UserAdminData::from($user->load(['spouse', 'vacations', 'availability', 'rosterLocations'])),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $user->update(Arr::except($request->validated(), ['id', 'uuid']));

        session()?->flash('flash.message', "$user->name was successfully modified.");
        return Redirect::route('admin.users.edit', $user->fresh());
    }

    public function destroy(User $user): RedirectResponse
    {
        $name = $user->name;
        $user->delete();

        session()?->flash('flash.message', "$name was successfully deleted.");
        session()?->flash('flash.bannerStyle', 'warn');
        return Redirect::route('admin.users.index');
    }
}
