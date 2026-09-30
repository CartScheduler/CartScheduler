<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

test('visitor can see set password page', function () {
    $user = User::factory()->enabled()->state(['password' => null])->create();
    $token = Password::createToken($user);

    $this->get("/set-password/$user->id/$token")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Profile/SetPassword')
            ->has('editUser', fn (AssertableInertia $data) => $data
                ->where('email', $user->email)
                ->where('name', $user->name)
            )
            ->where('token', $token)
            ->where('siteName', config('app.name'))
        )
        ->assertOk();
});

test('user with set password is directed to login page', function () {
    $user = User::factory()->enabled()->state(['password' => 'password'])->create();

    $this->get("/set-password/$user->id/mock-text")
        ->assertRedirect('/login');
});

test('logged in user is directed to home page', function () {
    $user = User::factory()->enabled()->state(['password' => 'password'])->create();

    $this->actingAs($user)
        ->get("/set-password/$user->id/mock-text")
        ->assertRedirect('/');
});

test('visitor has invalid hashed email', function () {
    $user = User::factory()->enabled()->state(['password' => null])->create();

    // So we don't see the exception in the logs...
    $this->withoutExceptionHandling([NotFoundHttpException::class])
        ->getJson("/set-password/$user->id/".base64_encode('mock-text'))
        ->assertNotFound();
});

test('visitor can set password', function () {
    $user = User::factory()->enabled()->state(['password' => null])->create();
    $token = Password::createToken($user);

    $this->post('/set-password', [
        'password_confirmation' => 'password',
        'password' => 'password',
        'token' => $token,
        'email' => $user->email,
    ])
        ->assertRedirect('/login')
        ->assertSessionHas('flash.setPassword', 'Your password has been set. Please use it to log in.');

    $user->refresh();
    expect(Hash::check('password', $user->password))->toBeTrue();
});

test('set password validation is working', function () {
    $this->post('/set-password', [
        'password_confirmation' => 'abc123',
        'password' => 'password',
    ])
        ->assertRedirect()
        ->assertInvalid([
            'password',
            'token' => config('cart-scheduler.set_password_generic_error_message').'(100)',
            'email' => config('cart-scheduler.set_password_generic_error_message').'(200)',
        ]);
});

test('invalid hashed email fails properly', function () {
    $user = User::factory()->enabled()->state(['password' => null])->create();

    // So we don't see the exception in the logs...
    $this->withoutExceptionHandling([NotFoundHttpException::class])
        ->post('/set-password', [
            'password_confirmation' => 'password',
            'password' => 'password',
            'token' => base64_encode('mock-text'),
            'email' => $user->email,
        ])
        ->assertNotFound();
});
