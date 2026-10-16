<?php

namespace App\Http\Controllers;

use App\Mail\UserAccountCreated;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class ResendWelcomeEmailController extends Controller
{
    /**
     * @throws ModelNotFoundException
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($request->get('user_id'));
        if ($user->has_logged_in) {
            return $this->sendPasswordReset($user);
        }

        return $this->sendCreatePassword($user);
    }

    protected function sendPasswordReset(User $user): JsonResponse
    {
        $sent = Password::sendResetLink($user->only('email'));
        if ($sent && $sent === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'A password reset link has been sent to the user.',
            ]);
        }

        if ($sent === Password::RESET_THROTTLED) {
            return response()->json([
                'message' => 'Too many password reset attempts. Please try again later.',
            ], 429);
        }

        return response()->json([
            'message' => 'An unknown error occurred while sending the password reset link.',
        ], 500);
    }

    protected function sendCreatePassword(User $user): JsonResponse
    {
        Mail::to($user->email)->send(new UserAccountCreated($user));

        return response()->json([
            'message' => 'Welcome email was sent',
        ]);
    }
}
