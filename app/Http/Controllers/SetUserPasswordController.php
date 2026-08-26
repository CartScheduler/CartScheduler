<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SetUserPasswordController extends Controller
{
    /**
     * @throws HttpException
     * @throws NotFoundHttpException
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
            'token' => $token,
            'siteName' => config('app.name'),
        ]);
    }

    /**
     * @throws HttpException
     * @throws NotFoundHttpException
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $this->validate(
            request: $request,
            rules: [
                'password_confirmation' => ['required'],
                'password' => ['required', 'confirmed'],
                'token' => ['required', 'string'],
                'email' => ['required', 'email', 'exists:users,email'],
            ],
            messages: [
                'token.required' => config('cart-scheduler.set_password_generic_error_message').'(100)',
                'email.required' => config('cart-scheduler.set_password_generic_error_message').'(200)',
            ]
        );

        $user = User::where('email', '=', $data['email'])->firstOrFail();

        abort_unless(Password::tokenExists($user, $data['token']), SymfonyResponse::HTTP_NOT_FOUND);

        $status = Password::reset(
            credentials: $request->only('email', 'password', 'password_confirmation', 'token'),
            callback: static function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        // Password::reset has already written the password through its callback.
        // Repeating it here ignored the $status it returned, so a rejected reset
        // still set the password — the token check above was the only thing
        // standing in the way.
        abort_unless($status === Password::PASSWORD_RESET, SymfonyResponse::HTTP_NOT_FOUND);

        session()?->flash('flash.setPassword', 'Your password has been set. Please use it to log in.');

        return Redirect::route('login');
    }
}
