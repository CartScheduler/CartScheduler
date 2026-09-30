<?php

use App\Actions\UserNeedsToUpdateAvailability;
use App\Models\User;
use App\Models\UserAvailability;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

test('execute returns false for null user', function () {
    $settings = GeneralSettings::fake([]);
    $userNeedsToUpdateAvailability = new UserNeedsToUpdateAvailability(settings: $settings);

    $result = $userNeedsToUpdateAvailability->execute(user: null);

    expect($result)->toBeFalse();
});

test('execute returns false when user availability is disabled', function () {
    $settings = GeneralSettings::fake([
        'enableUserAvailability' => false,
    ]);

    $user = $this->createMock(User::class);

    $userNeedsToUpdateAvailability = new UserNeedsToUpdateAvailability(settings: $settings);

    $result = $userNeedsToUpdateAvailability->execute(user: $user);

    expect($result)->toBeFalse();
});

test('when availability is enabled and availability is falsy', function () {
    $settings = GeneralSettings::fake([
        'enableUserAvailability' => true,
    ]);

    $user = $this->mock(User::class, fn (MockInterface $mock) => $mock
        ->shouldReceive('load')->andReturnSelf()
        ->shouldReceive('getAttribute')->andReturnNull()
    );

    $userNeedsToUpdateAvailability = new UserNeedsToUpdateAvailability(settings: $settings);

    expect($userNeedsToUpdateAvailability->execute(user: $user))->toBeTrue();
});

test('when availability is enabled and user has never defined it', function () {
    $settings = GeneralSettings::fake([
        'enableUserAvailability' => true,
    ]);

    $dateTime = $this->mock(Carbon::class, fn (MockInterface $mock) => $mock
        ->shouldReceive('eq')->andReturnTrue()
    );

    $availability = $this->mock(UserAvailability::class, fn (MockInterface $mock) => $mock
        ->shouldReceive('getAttribute')->with('created_at')->andReturn($dateTime)
        ->shouldReceive('getAttribute')->andReturn(false)
    );

    $user = $this->mock(User::class, fn (MockInterface $mock) => $mock
        ->shouldReceive('load')->andReturnSelf()
        ->shouldReceive('getAttribute')->andReturn($availability)
    );

    $userNeedsToUpdateAvailability = new UserNeedsToUpdateAvailability(settings: $settings);

    expect($userNeedsToUpdateAvailability->execute(user: $user))->toBeTrue();
});

test('when availability is enabled and user has not updated it within 1 month', function () {
    $settings = GeneralSettings::fake([
        'enableUserAvailability' => true,
    ]);

    $dateTime = $this->mock(Carbon::class, fn (MockInterface $mock) => $mock
        ->shouldReceive('diffInMonths')->andReturn(1)
    );

    $availability = $this->mock(UserAvailability::class, fn (MockInterface $mock) => $mock
        ->shouldReceive('getAttribute')->andReturn($dateTime)
    );

    $user = $this->mock(User::class, fn (MockInterface $mock) => $mock
        ->shouldReceive('load')->andReturnSelf()
        ->shouldReceive('getAttribute')->andReturn($availability)
    );

    $userNeedsToUpdateAvailability = new UserNeedsToUpdateAvailability(settings: $settings);

    $result = $userNeedsToUpdateAvailability->execute(user: $user);

    expect($result)->toBeTrue();
});
