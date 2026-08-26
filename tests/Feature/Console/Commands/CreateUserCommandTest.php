<?php

use App\Console\Commands\CreateUserCommand;
use App\Mail\UserAccountCreated;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('create user command can create a user', function () {
    $userData = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'phone' => '1234567890',
        'gender' => 'male',
        'password' => 'password',
    ];
    $this->assertDatabaseCount('users', 0);
    $this->artisan(CreateUserCommand::class, $userData)
        ->expectsOutput('User Test User created successfully')
        ->assertExitCode(0);

    $userData['mobile_phone'] = $userData['phone'];
    $userData['role'] = 'admin';
    unset($userData['phone'], $userData['password']);

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseHas('users', $userData);
});

test('create user command sends email verification', function () {
    $userData = [
        'name' => 'MailTest User',
        'email' => 'mailtest@example.com',
        'phone' => '1234567890',
        'gender' => 'male',
    ];
    Mail::fake();
    $this->assertDatabaseCount('users', 0);
    $this->artisan(CreateUserCommand::class, $userData)
        ->expectsOutput('User MailTest User created successfully')
        ->assertExitCode(0);

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseHas('users', [
        'name' => 'MailTest User',
        'email' => 'mailtest@example.com',
        'role' => 'admin',
    ]);
    Mail::assertSent(UserAccountCreated::class, 1);
});

test('create multiple users', function () {
    User::factory()->enabled()->adminRoleUser()->create();

    $userData = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'phone' => '1234567890',
        'gender' => 'male',
        'password' => 'password',
    ];

    $this->assertDatabaseCount('users', 1);

    $this->artisan(CreateUserCommand::class, $userData)
        ->expectsOutput('Only one user can be created from this interface')
        ->assertExitCode(0);

    $this->assertDatabaseMissing('users', ['name' => 'Test User', 'email' => 'test@example.com']);
});
