<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SetUserPasswordController extends Controller
{
    /**
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function show(User $user, string $token): Response|RedirectResponse
    {
        if (Auth::user()) {
            return Redirect::route('dashboard');
        }

        /** If user already has a password set, send them to the login page so the password cannot be 're-defined' */
        if ($user->password) {
            return Redirect::route('login');
        }

        abort_unless(Password::tokenExists($user, $token), \Illuminate\Http\Response::HTTP_NOT_FOUND);

        return Inertia::render('Profile/SetPassword', [
            'editUser' => ['email' => $user->email, 'name' => $user->name],
            'token'    => $token,
            'siteName' => config('app.name'),
        ]);
    }

    /**
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $this->validate(
            request: $request,
            rules: [
                'password_confirmation' => ['required'],
                'password'              => ['required', 'confirmed'],
                'token'                 => ['required', 'string'],
                'email'                 => ['required', 'email', 'exists:users,email'],
            ],
            messages: [
                'token.required' => config('cart-scheduler.set_password_generic_error_message') . '(100)',
                'email.required' => config('cart-scheduler.set_password_generic_error_message') . '(200)',
            ]
        );

        $user = User::where('email', '=', $data['email'])->firstOrFail();

        abort_unless(Password::tokenExists($user, $data['token']), SymfonyResponse::HTTP_NOT_FOUND);

        $status = Password::reset(
            credentials: $request->only('email', 'password', 'password_confirmation', 'token'),
            callback: static function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        session()?->flash('flash.setPassword', "Your password has been set. Please use it to log in.");
        return Redirect::route('login');
    }
}
