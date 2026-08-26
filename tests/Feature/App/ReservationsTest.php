<?php

use App\Enums\DBPeriod;
use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Traits\SetConfig;

uses(RefreshDatabase::class);

uses(SetConfig::class);

test('female user can reserve and release', function () {
    $user = User::factory()->female()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-01-15 12:00:00');

    $this->travelTo($startDate);
    $location = Location::factory()
        ->allPublishers()
        ->has(Shift::factory()->everyDay9am())
        ->create();

    $this->assertDatabaseCount('shift_user', 0);

    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->addDay()->toDateString(),
    ])->assertOk();

    $this->assertDatabaseCount('shift_user', 1);
    $this->assertDatabaseHas('shift_user', [
        'shift_id' => $location->shifts[0]->id,
        'user_id' => $user->getKey(),
        'shift_date' => $startDate->addDay()->toDateString(),
    ]);

    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => false,
        'date' => $startDate->addDay()->toDateString(),
    ])
        ->assertOk();

    $this->assertDatabaseCount('shift_user', 0);
    $this->assertDatabaseMissing('shift_user', [
        'shift_id' => $location->shifts[0]->id,
        'user_id' => $user->getKey(),
        'shift_date' => $startDate->addDay()->toDateString(),
    ]);
});

test('female user cannot reserve last spot of shift requiring one brother', function () {
    $user = User::factory()->female()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-01-15 12:00:00');

    $this->travelTo($startDate);

    /** @var Location $location */
    $location = Location::factory()
        ->requiresBrother()
        ->threeVolunteers()
        ->has(
            Shift::factory()
                ->everyDay9am()
                ->hasAttached(
                    User::factory()
                        ->female()
                        ->count(2), ['shift_date' => $startDate->addDay()->toDateString()]
                )
        )
        ->create();

    $response = $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->addDay()->toDateString(),
    ])
        ->assertUnprocessable()
        ->assertInvalid('shift');

    $this->assertStringContainsStringIgnoringCase('needs to be a brother', $response->json('errors.shift.0'));
    $this->assertDatabaseCount('shift_user', 2);
});

test('male user can reserve and release', function () {
    $user = User::factory()->male()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-01-15 12:00:00');

    $this->travelTo($startDate);

    /** @var Location $location */
    $location = Location::factory()
        ->requiresBrother()
        ->threeVolunteers()
        ->has(
            Shift::factory()
                ->everyDay9am()
                ->hasAttached(
                    User::factory()
                        ->female()
                        ->count(2), ['shift_date' => $startDate->addDay()->toDateString()]
                )
        )
        ->create();

    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->addDay()->toDateString(),
    ])->assertOk();

    $this->assertDatabaseCount('shift_user', 3);
    $this->assertDatabaseHas('shift_user', [
        'shift_id' => $location->shifts[0]->id,
        'user_id' => $user->getKey(),
        'shift_date' => $startDate->addDay()->toDateString(),
    ]);

    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => false,
        'date' => $startDate->addDay()->toDateString(),
    ])->assertOk();

    $this->assertDatabaseCount('shift_user', 2);
    $this->assertDatabaseMissing('shift_user', [
        'shift_id' => $location->shifts[0]->id,
        'user_id' => $user->getKey(),
        'shift_date' => $startDate->addDay()->toDateString(),
    ]);
});

test('not enabled user cannot reserve shifts', function () {
    $user = User::factory()->state(['is_enabled' => false])->male()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-01-15 12:00:00');

    $this->travelTo($startDate);
    $location = Location::factory()
        ->threeVolunteers()
        ->has(Shift::factory()->everyDay9am())
        ->create();

    $this->assertDatabaseCount('shift_user', 0);

    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->addDay()->toDateString(),
    ])->assertUnauthorized();

    $this->assertDatabaseCount('shift_user', 0);
});

test('user can reserve on the first day of reservation period', function () {
    $user = User::factory()->male()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-01-15 00:00:01');

    $this->travelTo($startDate);
    $location = Location::factory()
        ->has(Shift::factory()->everyDay9am())
        ->create();

    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->toDateString(),
    ])->assertOk();

    $this->assertDatabaseCount('shift_user', 1);
    $this->assertDatabaseHas('shift_user', [
        'shift_id' => $location->shifts[0]->id,
        'user_id' => $user->getKey(),
        'shift_date' => $startDate->toDateString(),
    ]);
});

test('user can reserve last day of reservation period', function () {
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '00:00');

    $user = User::factory()->male()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-01-11 00:00:01');

    // Wednesday
    $this->travelTo($startDate);
    $location = Location::factory()
        ->has(Shift::factory()->everyDay9am())
        ->create();

    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->setDay(15)->toDateString(), // set to the last day which is a Sunday
    ])->assertOk();

    $this->assertDatabaseCount('shift_user', 1);
    $this->assertDatabaseHas('shift_user', [
        'shift_id' => $location->shifts[0]->id,
        'user_id' => $user->getKey(),
        'shift_date' => $startDate->setDay(15)->toDateString(),
    ]);
});

test('user cannot reserve day before today', function () {
    $user = User::factory()->male()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-01-11 00:00:01');

    // Wednesday
    $this->travelTo($startDate);
    $location = Location::factory()
        ->has(Shift::factory()->everyDay9am())
        ->create();

    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->subDay()->toDateString(),
    ])->assertInvalid('date');
    $this->assertDatabaseCount('shift_user', 0);
});

test('user cannot reserve day after last day of reservation period', function () {
    $this->setConfig(1, DBPeriod::Week, true, 'SUN', '00:00');

    $user = User::factory()->male()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-01-11 00:00:01');

    // Wednesday
    $this->travelTo($startDate);
    $location = Location::factory()
        ->has(Shift::factory()->everyDay9am())
        ->create();

    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->addWeek()->toDateString(),
    ])
        ->assertUnprocessable()
        ->assertInvalid('date');
    $this->assertDatabaseCount('shift_user', 0);
});

test('user cannot reserve full shift', function () {
    $user = User::factory()->male()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-01-15 12:00:00');

    $this->travelTo($startDate);

    /** @var Location $location */
    $location = Location::factory()
        ->requiresBrother()
        ->threeVolunteers()
        ->has(
            Shift::factory()
                ->everyDay9am()
                ->hasAttached(
                    User::factory()
                        ->male()
                        ->count(3), ['shift_date' => $startDate->addDay()->toDateString()]
                )
        )
        ->create();

    $response = $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->addDay()->toDateString(),
    ])->assertUnprocessable();

    expect($response->json('error_code'))->toBe(100);
    $this->assertDatabaseCount('shift_user', 3);
});

test('user cannot reserve already reserved shift', function () {
    $user = User::factory()->male()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-01-15 12:00:00');

    $this->travelTo($startDate);

    /** @var Location $location */
    $location = Location::factory()
        ->requiresBrother()
        ->threeVolunteers()
        ->has(
            Shift::factory()
                ->everyDay9am()
                ->hasAttached(
                    $user, ['shift_date' => $startDate->addDay()->toDateString()]
                )
        )
        ->create();

    $this->assertDatabaseCount('shift_user', 1);

    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->addDay()->toDateString(),
    ])
        ->assertUnprocessable()
        ->assertInvalid('shift');

    $this->assertDatabaseCount('shift_user', 1);
});

test('male user can reserve shift that does not require male with only females occupying', function () {
    $user = User::factory()->male()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-01-15 12:00:00');

    $this->travelTo($startDate);

    /** @var Location $location */
    $location = Location::factory()
        ->allPublishers()
        ->threeVolunteers()
        ->has(
            Shift::factory()
                ->everyDay9am()
                ->hasAttached(
                    User::factory()
                        ->female()
                        ->count(2), ['shift_date' => $startDate->addDay()->toDateString()]
                )
        )
        ->create();

    $this->assertDatabaseCount('shift_user', 2);

    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->addDay()->toDateString(),
    ])
        ->assertOk();

    $this->assertDatabaseCount('shift_user', 3);
});

test('male user can reserve shift that does require male with only females occupying', function () {
    $user = User::factory()->male()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-01-15 12:00:00');

    $this->travelTo($startDate);

    /** @var Location $location */
    $location = Location::factory()
        ->requiresBrother()
        ->threeVolunteers()
        ->has(
            Shift::factory()
                ->everyDay9am()
                ->hasAttached(
                    User::factory()
                        ->female()
                        ->count(2), ['shift_date' => $startDate->addDay()->toDateString()]
                )
        )
        ->create();

    $this->assertDatabaseCount('shift_user', 2);

    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->addDay()->toDateString(),
    ])
        ->assertOk();

    $this->assertDatabaseCount('shift_user', 3);
});

test('male user can reserve shift that does require male with only males occupying', function () {
    $user = User::factory()->male()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-01-15 12:00:00');

    $this->travelTo($startDate);

    /** @var Location $location */
    $location = Location::factory()
        ->requiresBrother()
        ->threeVolunteers()
        ->has(
            Shift::factory()
                ->everyDay9am()
                ->hasAttached(
                    User::factory()
                        ->male()
                        ->count(2), ['shift_date' => $startDate->addDay()->toDateString()]
                )
        )
        ->create();

    $this->assertDatabaseCount('shift_user', 2);

    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->addDay()->toDateString(),
    ])
        ->assertOk();

    $this->assertDatabaseCount('shift_user', 3);
});

test('female user can reserve shift that does not require male with only females occupying', function () {
    $user = User::factory()->female()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-01-15 12:00:00');

    $this->travelTo($startDate);

    /** @var Location $location */
    $location = Location::factory()
        ->allPublishers()
        ->threeVolunteers()
        ->has(
            Shift::factory()
                ->everyDay9am()
                ->hasAttached(
                    User::factory()
                        ->female()
                        ->count(2), ['shift_date' => $startDate->addDay()->toDateString()]
                )
        )
        ->create();

    $this->assertDatabaseCount('shift_user', 2);

    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->addDay()->toDateString(),
    ])->assertOk();

    $this->assertDatabaseCount('shift_user', 3);
});

test('female user cannot reserve shift that requires brother with only females occupying', function () {
    $user = User::factory()->female()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-01-15 12:00:00');

    $this->travelTo($startDate);

    /** @var Location $location */
    $location = Location::factory()
        ->requiresBrother()
        ->threeVolunteers()
        ->has(
            Shift::factory()
                ->everyDay9am()
                ->hasAttached(
                    User::factory()
                        ->female()
                        ->count(2), ['shift_date' => $startDate->addDay()->toDateString()]
                )
        )
        ->create();

    $this->assertDatabaseCount('shift_user', 2);

    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->addDay()->toDateString(),
    ])
        ->assertUnprocessable()
        ->assertInvalid('shift');

    $this->assertDatabaseCount('shift_user', 2);
});

test('user cannot reserve at inactive location', function () {
    $user = User::factory()->male()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-01-15 12:00:00');

    $this->travelTo($startDate);
    $location = Location::factory()
        ->requiresBrother()
        ->threeVolunteers()
        ->state(['is_enabled' => false])
        ->has(Shift::factory()->everyDay9am())
        ->create();

    $this->assertDatabaseCount('shift_user', 0);

    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->addDay()->toDateString(),
    ])
        ->assertUnprocessable()
        ->assertInvalid('location');

    $this->assertDatabaseCount('shift_user', 0);
});

test('user cannot reserve a disabled shift', function () {
    $user = User::factory()->enabled()->male()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-01-15 12:00:00');

    $this->travelTo($startDate);

    /** @var Location $location */
    $location = Location::factory()
        ->requiresBrother()
        ->threeVolunteers()
        ->has(
            Shift::factory()
                ->everyDay9am()
                ->state(['is_enabled' => false])
        )
        ->create();

    $this->assertDatabaseCount('shift_user', 0);

    $this->actingAs($user)
        ->postJson('/reserve-shift', [
            'location' => $location->id,
            'shift' => $location->shifts[0]->id,
            'do_reserve' => true,
            'date' => $startDate->addDay()->toDateString(),
        ])
        ->assertUnprocessable()
        ->assertInvalid('shift');

    $this->assertDatabaseCount('shift_user', 0);
});

test('user cannot reserve an overlapping shift', function () {
    $user = User::factory()->male()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-01-15 12:00:00');
    $nextDay = $startDate->addDay()->toDateString();

    $this->travelTo($startDate);

    /** @var Location $location */
    $location = Location::factory()
        ->requiresBrother()
        ->threeVolunteers()
        ->has(
            Shift::factory()
                ->everyDay9am()
                ->hasAttached($user, ['shift_date' => $nextDay])
        )
        ->has(
            Shift::factory()
                ->everyDay1230pm()
                ->state(['start_time' => '10:30:00'])
        )
        ->create();

    $this->assertDatabaseCount('shift_user', 1);

    [$firstShift, $secondShift] = $location->shifts;

    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $secondShift->id,
        'do_reserve' => true,
        'date' => $nextDay,
    ])
        ->assertUnprocessable()
        ->assertInvalid('shift')
        ->assertContainsStringIgnoringCase('message', "you're already assigned")
        ->assertContainsStringIgnoringCase(
            'message',
            $location->name.' - '.$firstShift->start_time.' and '.$firstShift->end_time
        );

    $this->assertDatabaseCount('shift_user', 1);
});

test('user cannot reserve a shift on day where shift is not enabled', function () {
    $user = User::factory()->male()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-01-15 12:00:00');

    $this->travelTo($startDate);

    /** @var Location $location */
    $location = Location::factory()
        ->requiresBrother()
        ->threeVolunteers()
        ->has(
            Shift::factory()
                ->everyDay9am()
                ->state(['day_monday' => false])
        )
        ->create();

    $this->assertDatabaseCount('shift_user', 0);

    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->addDay()->toDateString(),
    ])
        ->assertUnprocessable()
        ->assertInvalid('shift');

    $this->assertDatabaseCount('shift_user', 0);
});

test('user cannot see inactive locations', function () {
    $this->setConfig(1, DBPeriod::Week, true, null, '12:00');
    $user = User::factory()->male()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-01-15 12:00:00');

    $this->travelTo($startDate);
    $locations = Location::factory()
        ->threeVolunteers()
        ->count(2)
        ->has(
            Shift::factory()
                ->everyDay9am()
                ->hasAttached(
                    User::factory()->count(2)->male(),
                    ['shift_date' => $startDate->addDay()->toDateString()]
                )
        )
        ->create();

    $this->assertDatabaseCount('locations', 2);
    $this->actingAs($user)->getJson("/shifts/{$startDate->addDay()->toDateString()}")
        ->assertOk()
        ->assertJsonCount(2, 'locations')
        ->assertJsonCount(7, 'freeShifts')
        ->assertJsonPath("freeShifts.{$startDate->toDateString()}.volunteer_count", fn (int $val) => $val === 0)
        ->assertJsonPath("freeShifts.{$startDate->toDateString()}.max_allowed", fn (int $val) => $val === 6)
        ->assertJsonPath("freeShifts.{$startDate->addDay()->toDateString()}.volunteer_count",
            fn (int $val) => $val === 4)
        ->assertJsonPath("freeShifts.{$startDate->addDay()->toDateString()}.max_allowed",
            fn (int $val) => $val === 6);

    // Disable 1 location. The assertions should halve...
    $locations[0]->is_enabled = false;
    $locations[0]->save();

    $this->actingAs($user)->getJson("/shifts/{$startDate->addDay()->toDateString()}")
        ->assertOk()
        ->assertJsonCount(1, 'locations')
        ->assertJsonCount(7, 'freeShifts')
        ->assertJsonPath("freeShifts.{$startDate->toDateString()}.volunteer_count", fn (int $val) => $val === 0)
        ->assertJsonPath("freeShifts.{$startDate->toDateString()}.max_allowed", fn (int $val) => $val === 3)
        ->assertJsonPath("freeShifts.{$startDate->addDay()->toDateString()}.volunteer_count",
            fn (int $val) => $val === 2)
        ->assertJsonPath("freeShifts.{$startDate->addDay()->toDateString()}.max_allowed",
            fn (int $val) => $val === 3);
});

test('user can see locations that are only displayed on limited days of the week on last day of period', function () {
    $this->setConfig(1, DBPeriod::Month, false, 'MON', '12:00');

    $user = User::factory()->enabled()->create();

    $startDate = CarbonImmutable::createFromTimeString('2024-05-22 7:00:00');

    $locations = Location::factory()
        ->threeVolunteers()
        ->count(3)
        ->has(Shift::factory()->everyDay9am())
        ->has(Shift::factory()->everyDay1230pm())
        ->create();

    $locations->last()->shifts->each(fn (Shift $shift) => $shift->setRawAttributes([
        'day_monday' => false,
        'day_tuesday' => false,
        'day_wednesday' => false,
        'day_thursday' => false,
        'day_friday' => false,
        'day_saturday' => true,
        'day_sunday' => true,
        'available_to' => '2024-07-31',
    ])->save());

    $sortedLocations = $locations->sortBy('name')->values();

    $this->travelTo($startDate);

    $this->actingAs($user)
        ->getJson('/shifts/2024-06-30') // previously, this date would not return all locations
        ->assertOk()
        ->assertJsonCount(3, 'locations')
        ->assertJsonPath('locations.0.name', $sortedLocations[0]->name)
        ->assertJsonPath('locations.1.name', $sortedLocations[1]->name)
        ->assertJsonPath('locations.2.name', $sortedLocations[2]->name)
        ->assertJsonCount(2, 'locations.0.shifts')
        ->assertJsonCount(2, 'locations.1.shifts')
        ->assertJsonCount(2, 'locations.2.shifts');
});

test('user cannot see disabled shifts', function () {
    $this->setConfig(1, DBPeriod::Week, true, null, '12:00');
    $user = User::factory()->male()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-01-15 12:00:00');

    $this->travelTo($startDate);

    $locations = Location::factory()
        ->threeVolunteers()
        ->count(2)
        ->sequence(['name' => 'Location 1'], ['name' => 'Location 2'])
        ->has(
            Shift::factory()
                ->count(1)
                ->everyDay9am()
                ->hasAttached(
                    User::factory()->count(2)->male(),
                    ['shift_date' => $startDate->addDay()->toDateString()]
                )
        )
        ->create();

    $this->assertDatabaseCount('shifts', 2);

    $this->actingAs($user)->getJson("/shifts/{$startDate->addDay()->toDateString()}")
        ->assertOk()
        ->assertJsonCount(0, 'shifts')
        ->assertJsonCount(2, 'locations')
        ->assertJsonCount(1, 'locations.0.shifts')
        ->assertJsonCount(1, 'locations.1.shifts')
        ->assertJsonCount(7, 'freeShifts')
        ->assertJsonPath("freeShifts.{$startDate->toDateString()}.volunteer_count", fn (int $val) => $val === 0)
        ->assertJsonPath("freeShifts.{$startDate->toDateString()}.max_allowed", fn (int $val) => $val === 6)
        ->assertJsonPath("freeShifts.{$startDate->addDay()->toDateString()}.volunteer_count",
            fn (int $val) => $val === 4)
        ->assertJsonPath("freeShifts.{$startDate->addDay()->toDateString()}.max_allowed",
            fn (int $val) => $val === 6);

    // Disable 1 location. The assertions should halve...
    $locations[0]->shifts[0]->is_enabled = false;
    $locations[0]->shifts[0]->save();

    $this->actingAs($user)->getJson("/shifts/{$startDate->addDay()->toDateString()}")
        ->assertOk()
        ->assertJsonCount(0, 'shifts')
        ->assertJsonCount(2, 'locations')
        ->assertJsonCount(0, 'locations.0.shifts')
        ->assertJsonCount(1, 'locations.1.shifts')
        ->assertJsonCount(7, 'freeShifts')
        ->assertJsonPath("freeShifts.{$startDate->toDateString()}.volunteer_count", fn (int $val) => $val === 0)
        ->assertJsonPath("freeShifts.{$startDate->toDateString()}.max_allowed", fn (int $val) => $val === 3)
        ->assertJsonPath("freeShifts.{$startDate->addDay()->toDateString()}.volunteer_count",
            fn (int $val) => $val === 2)
        ->assertJsonPath("freeShifts.{$startDate->addDay()->toDateString()}.max_allowed",
            fn (int $val) => $val === 3);
});

test('user cannot reserve daily released shifts beyond month', function () {
    $this->setConfig(1, DBPeriod::Month, true, null, '00:00');

    $user = User::factory()->male()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-02-08 00:00:00');

    $this->travelTo($startDate);

    $location = Location::factory()
        ->threeVolunteers()
        ->has(Shift::factory()->everyDay9am())
        ->create();

    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->addMonth()->subDay()->toDateString(),
    ])
        ->assertOk()
        ->assertContent('Reservation made');

    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->addMonth()->toDateString(),
    ])
        ->assertUnprocessable()
        ->assertInvalid('date');
});

test('user cannot reserve daily released at time shifts beyond month', function () {
    $this->setConfig(1, DBPeriod::Month, true, null, '12:00');

    $user = User::factory()->male()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-02-08 00:00:00');

    $location = Location::factory()
        ->threeVolunteers()
        ->has(Shift::factory()->everyDay9am())
        ->create();

    $this->travelTo($startDate);
    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->addMonth()->subDay()->toDateString(),
    ])
        ->assertUnprocessable()
        ->assertInvalid('date');

    $this->travelTo($startDate->midDay());
    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->addMonth()->subDay()->toDateString(),
    ])
        ->assertOk()
        ->assertContent('Reservation made');
});

test('user cannot reserve daily released shifts beyond week', function () {
    $this->setConfig(1, DBPeriod::Week, true, null, '00:00');

    $user = User::factory()->male()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-02-16 00:00:00');

    $location = Location::factory()
        ->threeVolunteers()
        ->has(Shift::factory()->everyDay9am())
        ->create();

    $this->travelTo($startDate);
    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->addWeek()->toDateString(),
    ])
        ->assertUnprocessable()
        ->assertInvalid('date');

    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->addWeek()->subDay()->toDateString(),
    ])
        ->assertOk()
        ->assertContent('Reservation made');
});

test('user cannot reserve daily released at time shifts beyond week', function () {
    $this->setConfig(1, DBPeriod::Week, true, null, '12:00');

    $user = User::factory()->male()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-02-16 00:00:00');

    $location = Location::factory()
        ->threeVolunteers()
        ->has(Shift::factory()->everyDay9am())
        ->create();

    $this->travelTo($startDate);
    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->addWeek()->subDay()->toDateString(),
    ])
        ->assertUnprocessable()
        ->assertInvalid('date');

    $this->travelTo($startDate->midDay());
    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->addWeek()->subDay()->toDateString(),
    ])
        ->assertOk()
        ->assertContent('Reservation made');
});

test('user cannot reserve period shifts released shifts beyond allowed time with monthly release', function () {
    $this->setConfig(1, DBPeriod::Month, false, null, '00:00');

    $user = User::factory()->male()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-02-08 00:00:00');

    $location = Location::factory()
        ->threeVolunteers()
        ->has(Shift::factory()->everyDay9am())
        ->create();

    $this->travelTo($startDate);
    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->addMonths(2)->firstOfMonth()->toDateString(),
    ])
        ->assertUnprocessable()
        ->assertInvalid('date');

    $this->travelTo($startDate->midDay());
    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        // Used the syntax below to be a more obvious comparison to the above.
        'date' => $startDate->addMonths(2)->firstOfMonth()->subDay()->toDateString(),
    ])
        ->assertOk()
        ->assertContent('Reservation made');
});

test('user cannot reserve released shifts beyond allowed time with weekly release', function () {
    /*
     * February 2023
     * Mo Tu We Th Fr Sa Su
     *        1  2  3  4  5
     *  6  7  8  9 10 11 12 <- Wednesday 8th
     * 13 14 15 16 17 18 19 <- 18th allowed; Sunday 19th & rest of the month, not allowed
     * 20 21 22 23 24 25 26
     * 27 28
     */
    $this->setConfig(1, DBPeriod::Week, false, 'SUN', '00:00');

    $user = User::factory()->male()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-02-08 00:00:00');

    $location = Location::factory()
        ->threeVolunteers()
        ->has(Shift::factory()->everyDay9am())
        ->create();

    $this->travelTo($startDate);
    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->setDay(19)->toDateString(),
    ])
        ->assertUnprocessable()
        ->assertInvalid('date');

    $this->travelTo($startDate->midDay());
    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->setDay(18)->toDateString(),
    ])
        ->assertOk()
        ->assertContent('Reservation made');
});

test('user cannot reserve new shifts before allowed time', function () {
    /*
     * February 2023
     * Mo Tu We Th Fr Sa Su
     *        1  2  3  4  5
     *  6  7  8  9 10 11 12
     * 13 14 15 16 17 18 19 <- Date is Monday 13th; before 12:30, only up until 19th is allowed
     * 20 21 22 23 24 25 26 <- After 12:30, user can reserve until the 26th
     * 27 28
     */
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');

    $user = User::factory()->male()->create();

    $startDate = CarbonImmutable::createFromTimeString('2023-02-13 12:29:00');

    $location = Location::factory()
        ->threeVolunteers()
        ->has(Shift::factory()->everyDay9am())
        ->create();

    $this->travelTo($startDate);
    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->setDay(26)->toDateString(),
    ])
        ->assertUnprocessable()
        ->assertInvalid('date');

    $this->travelTo($startDate->midDay()->addMinutes(30));
    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => true,
        'date' => $startDate->setDay(26)->toDateString(),
    ])
        ->assertOk()
        ->assertContent('Reservation made');
});

test('user can see shifts and can only access approved data', function () {
    $users = User::factory()->enabled()->count(4)->create();
    $date = '2023-01-03';
    // A Tuesday
    Location::factory()
        ->has(
            Shift::factory()
                ->hasAttached($users, ['shift_date' => $date])
                ->everyDay9am()
        )
        ->create();

    $this->travelTo('2023-01-02 09:00:00');

    $this->assertDatabaseCount('shift_user', $users->count());

    $this->actingAs($users[0])
        ->getJson("/shifts/$date")
        ->assertOk()
        ->assertJsonCount($users->count(), 'locations.0.shifts.0.volunteers')
        ->assertJsonFragment([
            'name' => $users[0]->name, 'mobile_phone' => $users[0]->mobile_phone, 'uuid' => $users[0]->uuid,
        ])
        ->assertJsonFragment(['name' => $users[1]->name, 'mobile_phone' => $users[1]->mobile_phone])
        ->assertJsonFragment(['name' => $users[2]->name, 'mobile_phone' => $users[2]->mobile_phone])
        ->assertJsonFragment(['name' => $users[3]->name, 'mobile_phone' => $users[3]->mobile_phone])
        // Make sure we're not leaking non-needed data
        ->tap(fn (TestResponse $response) => $response->assertExactJsonStructure(
            structure: [
                '*' => [
                    'name',
                    'uuid',
                    'gender',
                    'mobile_phone',
                ],
            ],
            responseData: $response->json('locations.0.shifts.0.volunteers'),
        ))
        ->assertJsonMissingPath('locations.0.shifts.0.volunteers.1.id')
        ->assertJsonMissingPath('locations.0.shifts.0.volunteers.2.id');
});

test('remove volunteer when not attached to shift', function () {
    $location = Location::factory()
        ->threeVolunteers()
        ->allPublishers()
        ->has(Shift::factory()->everyDay9am())
        ->create();

    $user = User::factory()->enabled()->create();

    $this->travelTo('2023-01-02 09:00:00');
    $this->actingAs($user)->postJson('/reserve-shift', [
        'location' => $location->id,
        'shift' => $location->shifts[0]->id,
        'do_reserve' => false, // setting this to false should fail
        'date' => '2023-01-03',
    ])
        ->assertUnprocessable()
        ->assertInvalid('shift');
});
