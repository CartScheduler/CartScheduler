<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

/**
 * Not used at this time
 * @codeCoverageIgnore
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function create(array $input): User
    {
        Validator::make($input, [
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'mobile_phone' => ['required', 'string', 'max:255'],
            'password'     => $this->passwordRules(),
            'terms'        => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        return DB::transaction(fn() => tap(User::create([
            'name'         => $input['name'],
            'email'        => $input['email'],
            'mobile_phone' => $input['mobile_phone'],
            'password'     => Hash::make($input['password']),
        ]), function (User $user) {
        }));
    }
}
