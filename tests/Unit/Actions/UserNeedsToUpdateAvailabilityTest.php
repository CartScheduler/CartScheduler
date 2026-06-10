<?php

namespace Tests\Unit\Actions;

use App\Actions\UserNeedsToUpdateAvailability;
use App\Models\User;
use App\Models\UserAvailability;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery\MockInterface;
use Tests\TestCase;

class UserNeedsToUpdateAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_returns_false_for_null_user(): void
    {
        $settings                      = GeneralSettings::fake([]);
        $userNeedsToUpdateAvailability = new UserNeedsToUpdateAvailability(settings: $settings);

        $result = $userNeedsToUpdateAvailability->execute(user: null);

        $this->assertFalse($result);
    }

    public function test_execute_returns_false_when_user_availability_is_disabled(): void
    {
        $settings = GeneralSettings::fake([
            'enableUserAvailability' => false,
        ]);

        $user = $this->createMock(User::class);

        $userNeedsToUpdateAvailability = new UserNeedsToUpdateAvailability(settings: $settings);

        $result = $userNeedsToUpdateAvailability->execute(user: $user);

        $this->assertFalse($result);
    }

    public function test_when_availability_is_enabled_and_availability_is_falsy(): void
    {
        $settings = GeneralSettings::fake([
            'enableUserAvailability' => true,
        ]);

        $user = $this->mock(User::class, fn(MockInterface $mock) => $mock
            ->shouldReceive('load')->andReturnSelf()
            ->shouldReceive('getAttribute')->andReturnNull()
        );

        $userNeedsToUpdateAvailability = new UserNeedsToUpdateAvailability(settings: $settings);

        $this->assertTrue($userNeedsToUpdateAvailability->execute(user: $user));
    }

    public function test_when_availability_is_enabled_and_user_has_never_defined_it(): void
    {
        $settings = GeneralSettings::fake([
            'enableUserAvailability' => true,
        ]);

        $dateTime = $this->mock(Carbon::class, fn(MockInterface $mock) => $mock
            ->shouldReceive('eq')->andReturnTrue()
        );

        $availability = $this->mock(UserAvailability::class, fn(MockInterface $mock) => $mock
            ->shouldReceive('getAttribute')->with('created_at')->andReturn($dateTime)
            ->shouldReceive('getAttribute')->andReturn(false)
        );

        $user = $this->mock(User::class, fn(MockInterface $mock) => $mock
            ->shouldReceive('load')->andReturnSelf()
            ->shouldReceive('getAttribute')->andReturn($availability)
        );

        $userNeedsToUpdateAvailability = new UserNeedsToUpdateAvailability(settings: $settings);

        $this->assertTrue($userNeedsToUpdateAvailability->execute(user: $user));
    }

    public function test_when_availability_is_enabled_and_user_has_not_updated_it_within_1_month(): void
    {
        $settings = GeneralSettings::fake([
            'enableUserAvailability' => true,
        ]);

        $dateTime = $this->mock(Carbon::class, fn(MockInterface $mock) => $mock
            ->shouldReceive('diffInMonths')->andReturn(1)
        );

        $availability = $this->mock(UserAvailability::class, fn(MockInterface $mock) => $mock
            ->shouldReceive('getAttribute')->andReturn($dateTime)
        );

        $user = $this->mock(User::class, fn(MockInterface $mock) => $mock
            ->shouldReceive('load')->andReturnSelf()
            ->shouldReceive('getAttribute')->andReturn($availability)
        );

        $userNeedsToUpdateAvailability = new UserNeedsToUpdateAvailability(settings: $settings);

        $result = $userNeedsToUpdateAvailability->execute(user: $user);

        $this->assertTrue($result);
    }
}
