<?php

use App\Actions\ErrorApiResource;
use App\Models\Location;
use App\Models\Shift;
use App\Models\ShiftUser;
use App\Models\User;
use App\Models\UserAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('non admin cannot get a list of users', function () {
    User::factory()->userRoleUser()->count(3)->create(['is_enabled' => true]);
    $nonAdminUser = User::factory()->userRoleUser()->create(['is_enabled' => true]);

    $location = Location::factory()
        ->allPublishers()
        ->threeVolunteers()
        ->has(Shift::factory()->everyDay9am())
        ->create();

    $date = '2023-01-03';

    // A Tuesday
    $shiftId = $location->shifts[0]->id;
    $this->actingAs($nonAdminUser)
        ->json('GET', "/admin/available-users-for-shift/$shiftId", ['date' => $date])
        ->assertForbidden();
});

test('admin can get a list of users', function () {
    $admin = User::factory()->adminRoleUser()
        ->has(UserAvailability::factory()->weekdays9To5(), 'availability')
        ->create(['is_enabled' => true]);
    $users = User::factory()->userRoleUser()
        ->has(UserAvailability::factory()->weekdays9To5(), 'availability')
        ->count(3)
        ->create(['is_enabled' => true]);
    $firstUser = $users->first();
    $secondUser = $users->get(1);

    $location = Location::factory()->requiresBrother()->create();

    /** @var Shift $shift */
    $shift = Shift::factory()->everyDay9am()->create([
        'location_id' => $location->id,
    ]);
    $date = '2023-01-03';
    // A Tuesday
    $shiftId = $shift->id;
    $this->actingAs($admin)
        ->json('GET', "/admin/available-users-for-shift/$shiftId", ['date' => $date])
        // 4 users in the system. Should have 'available' 4 users returned
        ->assertJsonCount(4)
        ->assertJsonPath('1.id', $firstUser->getKey());

    // Assign the first user to a shift
    ShiftUser::factory()->create([
        'shift_id' => $shiftId,
        'user_id' => $firstUser->getKey(),
        'shift_date' => $date,
    ]);
    $this->actingAs($admin)
        ->json('GET', "/admin/available-users-for-shift/$shiftId", ['date' => $date])
        // Should now only have 'available' 3 users returned
        ->assertJsonCount(3)
        ->assertJsonMissing(['*.id' => $firstUser->getKey()]);

    // Now test that the returned data doesn't include users who are already assigned to another shift at the same time
    $location = Location::factory()->requiresBrother()->create();

    /** @var Shift $shift2 */
    $shift2 = Shift::factory()->everyDay9am()->create([
        'location_id' => $location->id,
    ]);
    ShiftUser::factory()->create([
        'shift_id' => $shift2->id,
        'user_id' => $secondUser->getKey(),
        'shift_date' => $date,
    ]);
    $this->actingAs($admin)
        ->json('GET', "/admin/available-users-for-shift/$shift2->id", ['date' => $date])
        // Should now only have 'available' 3 users returned
        ->assertJsonCount(2)
        ->assertJsonMissing(['*.id' => $firstUser->getKey()])
        ->assertJsonMissing(['*.id' => $secondUser->getKey()]);
});

test('admin receives male volunteers when last spot requires brother shift', function () {
    $admin = User::factory()
        ->has(UserAvailability::factory()->weekdays9To5(), 'availability')
        ->adminRoleUser()
        ->create(['is_enabled' => true]);
    User::factory()
        ->male()
        ->has(UserAvailability::factory()->weekdays9To5(), 'availability')
        ->count(3)
        ->create(['is_enabled' => true]);
    $sisters = User::factory()
        ->female()
        ->has(UserAvailability::factory()->weekdays9To5(), 'availability')
        ->count(3)
        ->create(['is_enabled' => true]);

    $this->assertDatabaseCount('users', 7);

    $location = Location::factory()
        ->threeVolunteers()
        ->requiresBrother()
        ->create();

    /** @var Shift $shift */
    $shift = Shift::factory()->everyDay9am()->create([
        'location_id' => $location->id,
    ]);
    $shiftId = $shift->id;

    $date = '2023-01-03';

    // A Tuesday
    // Attach a sister to the shift
    ShiftUser::factory()->create([
        'shift_id' => $shiftId,
        'user_id' => $sisters->get(0)->getKey(),
        'shift_date' => $date,
    ]);

    $this->actingAs($admin)
        ->json('GET', "/admin/available-users-for-shift/$shiftId", ['date' => $date])
        // 7 users in the system, 1 female assigned. Should have 6 users returned
        ->assertJsonCount(6);

    // Attach another sister so now no more sisters should be returned on the next request
    ShiftUser::factory()->create([
        'shift_id' => $shift->id,
        'user_id' => $sisters->get(1)->getKey(),
        'shift_date' => $date,
    ]);

    $this->actingAs($admin)
        ->json('GET', "/admin/available-users-for-shift/$shiftId", ['date' => $date])
        // Should only have male users returned
        ->assertJsonCount(4)
        ->assertJsonPath('0.gender', 'male')
        ->assertJsonPath('1.gender', 'male')
        ->assertJsonPath('2.gender', 'male')
        ->assertJsonPath('3.gender', 'male');
});

test('list of users doesnt include disabled', function () {
    $admin = User::factory()
        ->adminRoleUser()
        ->has(UserAvailability::factory()->weekdays9To5(), 'availability')
        ->create(['is_enabled' => true]);
    $enabledUser = User::factory()
        ->userRoleUser()
        ->has(UserAvailability::factory()->weekdays9To5(), 'availability')
        ->count(4)
        ->create(['is_enabled' => true])
        ->get(0);
    User::factory()
        ->userRoleUser()
        ->has(UserAvailability::factory()->weekdays9To5(), 'availability')
        ->count(5)
        ->create(['is_enabled' => false]);

    $this->assertDatabaseCount('users', 10);

    $location = Location::factory()->requiresBrother()->create();

    /** @var Shift $shift */
    $shift = Shift::factory()->everyDay9am()->create([
        'location_id' => $location->id,
    ]);
    $date = '2023-01-03';

    // A Tuesday
    $shiftId = $shift->id;
    $this->actingAs($admin)
        ->json('GET', "/admin/available-users-for-shift/$shiftId", ['date' => $date])
        // 10 users in the system but should only have retrieve 5 active users because 5 are disabled
        ->assertJsonCount(5)
        ->assertJsonPath('0.id', $admin->getKey())
        ->assertJsonPath('1.id', $enabledUser->getKey());
});

test('admin can assign a user to a shift', function () {
    $admin = User::factory()
        ->adminRoleUser()
        ->has(UserAvailability::factory()->weekdays9To5(), 'availability')
        ->create(['is_enabled' => true]);
    $enabledUser = User::factory()
        ->userRoleUser()
        ->has(UserAvailability::factory()->weekdays9To5(), 'availability')
        ->count(4)
        ->create(['is_enabled' => true])
        ->get(0);
    $disabledUser = User::factory()
        ->userRoleUser()
        ->has(UserAvailability::factory()->weekdays9To5(), 'availability')
        ->count(5)
        ->create(['is_enabled' => false])
        ->get(0);

    $this->assertDatabaseCount('users', 10);

    $location = Location::factory()->requiresBrother()->create();

    /** @var Shift $shift */
    $shift = Shift::factory()->everyDay9am()->create([
        'location_id' => $location->id,
    ]);
    $this->travelTo('2023-01-02 09:00:00');
    $date = '2023-01-03';

    // A Tuesday
    $this->assertDatabaseCount('shift_user', 0);

    $this->actingAs($admin)
        ->putJson('/admin/toggle-shift-for-user', [
            'date' => $date,
            'do_reserve' => true,
            'location' => $location->id,
            'shift' => $shift->id,
            'user' => $enabledUser->getKey(),
        ]
        )
        ->assertOk();

    $this->assertDatabaseCount('shift_user', 1);
    $this->assertDatabaseHas('shift_user', [
        'shift_id' => $shift->id,
        'user_id' => $enabledUser->getKey(),
        'shift_date' => $date,
    ]);

    $this->actingAs($admin)
        ->deleteJson('/admin/toggle-shift-for-user', [
            'date' => $date,
            'do_reserve' => false,
            'location' => $location->id,
            'shift' => $shift->id,
            'user' => $enabledUser->getKey(),
        ]
        )->assertOk();

    $this->assertDatabaseCount('shift_user', 0);
    $this->assertDatabaseMissing('shift_user', [
        'shift_id' => $shift->id,
        'user_id' => $enabledUser->getKey(),
        'shift_date' => $date,
    ]);

    // Test that adding an 'inactive' user fails
    $this->actingAs($admin)
        ->putJson('/admin/toggle-shift-for-user', [
            'date' => $date,
            'do_reserve' => true,
            'location' => $location->id,
            'shift' => $shift->id,
            'user' => $disabledUser->getKey(),
        ]
        )->assertStatus(422)
        ->assertContainsStringIgnoringCase('message', 'has been disabled');
});

test('gender restrictions are enforced on shift requiring a brother', function () {
    $admin = User::factory()
        ->adminRoleUser()
        ->has(UserAvailability::factory()->weekdays9To5(), 'availability')
        ->create(['is_enabled' => true]);
    $users = User::factory()
        ->female()
        ->has(UserAvailability::factory()->weekdays9To5(), 'availability')
        ->count(3)
        ->create();

    $this->assertDatabaseCount('users', 4);

    $location = Location::factory()
        ->requiresBrother()
        ->threeVolunteers()
        ->state(['min_volunteers' => 3])
        ->create();

    /** @var Shift $shift */
    $shift = Shift::factory()->everyDay9am()->create([
        'location_id' => $location->id,
    ]);
    $this->travelTo('2023-01-02 09:00:00');
    $date = '2023-01-03';

    // A Tuesday
    $this->actingAs($admin)
        ->putJson('/admin/toggle-shift-for-user', [
            'date' => $date,
            'do_reserve' => true,
            'location' => $location->id,
            'shift' => $shift->id,
            'user' => $users->get(0)->getKey(),
        ]
        )->assertOk();
    $this->actingAs($admin)
        ->putJson('/admin/toggle-shift-for-user', [
            'date' => $date,
            'do_reserve' => true,
            'location' => $location->id,
            'shift' => $shift->id,
            'user' => $users->get(1)->getKey(),
        ]
        )->assertOk();

    $this->actingAs($admin)
        ->putJson('/admin/toggle-shift-for-user', [
            'date' => $date,
            'do_reserve' => true,
            'location' => $location->id,
            'shift' => $shift->id,
            'user' => $users->get(2)->getKey(),
        ]
        )
        ->assertStatus(422)
        ->assertContainsStringIgnoringCase('message', 'the last volunteer for this shift needs to be a brother');
});

test('volunteer cannot be assigned to an overlapping shift', function () {
    $admin = User::factory()
        ->enabled()
        ->adminRoleUser()
        ->has(UserAvailability::factory()->weekdays9To5(), 'availability')
        ->create();
    $user = User::factory()
        ->male()
        ->has(UserAvailability::factory()->weekdays9To5(), 'availability')
        ->create();

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

    $this->actingAs($admin)->putJson('/admin/toggle-shift-for-user', [
        'date' => $nextDay,
        'do_reserve' => true,
        'location' => $location->id,
        'shift' => $secondShift->id,
        'user' => $user->getKey(),
    ])
        ->assertUnprocessable()
        ->assertInvalid('shift')
        ->assertContainsStringIgnoringCase(
            'message',
            Str::of($user->name)
                ->append(' is already on a shift that overlaps this shift at ')
                ->append($location->name, ' between ')
                ->append(Carbon::parse($firstShift->start_time)->format('h:i a'), ' and ')
                ->append(Carbon::parse($firstShift->end_time)->format('h:i a'))
                ->value(),
        );

    $this->assertDatabaseCount('shift_user', 1);
});

test('volunteer can be moved to a different shift', function () {
    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);

    $date = CarbonImmutable::parse('2023-01-03');

    // A Tuesday
    $locations = Location::factory()
        ->threeVolunteers()
        ->allPublishers()
        ->count(2)
        ->has(Shift::factory()
            ->everyDay9am()
            ->hasAttached(User::factory()
                ->count(3)
                ->enabled(), ['shift_date' => $date]
            )
        )
        ->create();

    $shifts = $locations->map->shifts->flatten();

    /** @var Shift $firstShift */
    $firstShift = $shifts->first();

    /** @var Shift $secondShift */
    $secondShift = $shifts->last();

    $date2 = $date->addDay();

    // Add user 1 on shift 1 to the next day
    $firstShift->users->first()->attachShiftOnDate($firstShift, $date2);
    $firstShift->refresh();

    // Just to be sure we have the correct number of shifts
    expect($shifts)->toHaveCount(2);
    expect($firstShift->users)->toHaveCount(4);
    expect($secondShift->users)->toHaveCount(3);

    $this->assertDatabaseHas('shift_user', [
        'shift_id' => $firstShift->getKey(),
        'user_id' => $firstShift->users->first()->getKey(),
        'shift_date' => $date2->toDateString(),
    ]);

    // Remove a user from second shift - to enable a user to move into the spot
    $secondShift->users->last()->detachShiftOnDate($secondShift, $date);
    $secondShift->refresh();
    expect($secondShift->users)->toHaveCount(2);

    $this->actingAs($admin)
        ->putJson('/admin/move-volunteer-to-shift', [
            'date' => $date->toDateString(),
            'location_id' => $secondShift->location->id,
            'shift_id' => $secondShift->id,
            'old_shift_id' => $firstShift->id,
            'user_id' => $firstShift->users->last()->id,
        ])
        ->assertSuccessful();
    $firstShift->refresh();
    $secondShift->refresh();

    expect($firstShift->getUsersOnDate($date)->count())->toBe(2);
    expect($firstShift->getUsersOnDate($date2)->count())->toBe(1);
    expect($secondShift->users)->toHaveCount(3);
});

test('move volunteer with invalid location', function () {
    $admin = User::factory()->adminRoleUser()->create();

    $date = '2023-01-03';

    // A Tuesday
    $locations = Location::factory()
        ->count(2)
        ->allPublishers()
        ->threeVolunteers()
        ->sequence(['is_enabled' => true], ['is_enabled' => false])
        ->has(Shift::factory()
            ->everyDay9am()
            ->hasAttached(User::factory()
                ->count(3)
                ->enabled(), ['shift_date' => $date]
            )
        )
        ->create();

    $shifts = $locations->map->shifts->flatten();

    // Just to be sure we have the correct number of shifts
    expect($shifts)->toHaveCount(2);
    expect($shifts[0]->users)->toHaveCount(3);
    expect($shifts[1]->users)->toHaveCount(3);

    // Remove a user from second shift - to enable a user to move into the spot
    $shifts[1]->users()->detach($shifts[1]->users->last());
    $shifts[1]->refresh();
    expect($shifts[1]->users)->toHaveCount(2);

    $this->actingAs($admin)
        ->putJson('/admin/move-volunteer-to-shift', [
            'date' => $date,
            'location_id' => $shifts[1]->location->id,
            'shift_id' => $shifts[1]->id,
            'old_shift_id' => $shifts[0]->getKey(),
            'user_id' => $shifts[0]->users->last()->getKey(),
        ])
        ->assertUnprocessable()
        ->assertContainsStringIgnoringCase('message', 'The selected location id is invalid');
    $shifts->each->refresh();

    expect($shifts[0]->users)->toHaveCount(3);
    expect($shifts[1]->users)->toHaveCount(2);
});

test('volunteer cannot be moved to a full shift', function () {
    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);

    $locations = Location::factory()
        ->allPublishers()
        ->threeVolunteers()
        ->count(2)
        ->has(Shift::factory()
            ->everyDay9am()
            ->hasAttached(User::factory()
                ->userRoleUser()
                ->count(3)
                ->state(['is_enabled' => true]), ['shift_date' => '2023-01-03']
            ))
        ->create();

    $shifts = $locations->map->shifts->flatten();

    // Just to be sure we have the correct number of shifts
    expect($shifts)->toHaveCount(2);
    expect($shifts[0]->users)->toHaveCount(3);
    expect($shifts[1]->users)->toHaveCount(3);

    // Remove a user from second shift - to enable a user to move into the spot
    $date = '2023-01-03';

    // A Tuesday
    $this->actingAs($admin)
        ->putJson('/admin/move-volunteer-to-shift', [
            'date' => $date,
            'location_id' => $shifts[1]->location->id, // This is the location of shift volunteer will be moved to
            'shift_id' => $shifts[1]->id,
            'old_shift_id' => $shifts[0]->getKey(),
            'user_id' => $shifts[0]->users->last()->getKey(),
        ])
        ->assertunprocessable()
        ->assertJsonPath('error_code', ErrorApiResource::CODE_SHIFT_AT_MAX_CAPACITY);

    $shifts->each->refresh();
    expect($shifts[0]->users)->toHaveCount(3);
    expect($shifts[1]->users)->toHaveCount(3);
});

test('sister cannot be moved to a shift requiring a brother', function () {
    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);
    $sister = User::factory()->female()->create();

    /** @var Location $location */
    $location = Location::factory()
        ->requiresBrother()
        ->threeVolunteers()
        ->has(Shift::factory()
            ->everyDay9am()
            ->hasAttached(User::factory()
                ->female()
                ->count(2), ['shift_date' => '2023-01-03']
            )
        )
        ->create();

    $location2 = Location::factory()
        ->threeVolunteers()
        ->has(Shift::factory()->everyDay9am())
        ->create();

    $this->assertDatabaseCount('shift_user', 2);

    $date = '2023-01-03';

    // A Tuesday
    $this->actingAs($admin)
        ->putJson('/admin/move-volunteer-to-shift', [
            'date' => $date,
            'location_id' => $location->id,
            'shift_id' => $location->shifts[0]->id,
            'old_shift_id' => $location2->shifts[0]->id,
            'user_id' => $sister->id,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error_code', ErrorApiResource::CODE_BROTHER_REQUIRED);

    $this->assertDatabaseCount('shift_user', 2);
});

test('move volunteer when duplicate shifts with one disabled', function () {
    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);
    $location1Users = User::factory()->enabled()->count(3)->create();

    $date1 = '2023-01-03';
    $date2 = '2023-01-04';

    /** @var Collection<int, Location> $locations */
    $locations = collect();
    $locations[] = Location::factory()
        ->allPublishers()
        ->threeVolunteers()
        ->has(Shift::factory()
            ->everyDay9am()
            ->hasAttached($location1Users, ['shift_date' => $date1])
            // Additional check to ensure users aren't inadvertently removed from other shifts at the same time
            ->hasAttached($location1Users, ['shift_date' => $date2])
        )
        ->create();

    $locations[] = Location::factory()
        ->allPublishers()
        ->threeVolunteers()
        ->has(Shift::factory()
            ->count(2)
            ->everyDay9am()
            ->sequence(['is_enabled' => true], ['is_enabled' => false])
            ->hasAttached(User::factory()
                ->enabled()
                ->count(2), ['shift_date' => $date1]
            ))
        ->create();

    $locations->each->load(['shifts', 'shifts.users', 'shifts.location']);
    $shift1 = $locations[0]->shifts->first();
    $shift2 = $locations[1]->shifts->first();
    $shift3 = $locations[1]->shifts->last();

    // Just to be sure we have the correct number of shifts
    expect($shift1->getUsersOnDate($date1)->count())->toBe(3);
    expect($shift1->getUsersOnDate($date2)->count())->toBe(3);
    expect($shift2->users)->toHaveCount(2);
    expect($shift3->users)->toHaveCount(2);

    expect($shift1->is_enabled)->toBeTrue();
    $this->asserttrue($shift2->is_enabled);
    expect($shift3->is_enabled)->toBeFalse();

    $movingUserId = $shift1->users->last()->id;
    $this->actingAs($admin)
        ->putJson('/admin/move-volunteer-to-shift', [
            'date' => $date1,
            'location_id' => $shift2->location->id,
            'shift_id' => $shift2->id,
            'old_shift_id' => $shift1->first()->id,
            'user_id' => $movingUserId,
        ])
        ->assertSuccessful();

    $shift1->refresh();
    $shift2->refresh();
    $shift3->refresh();

    expect($shift1->getUsersOnDate($date1)->count())->toBe(2);
    expect($shift1->getUsersOnDate($date2)->count())->toBe(3);
    expect($shift2->users)->toHaveCount(3);
    expect($shift3->users)->toHaveCount(2);

    // Move the volunteer back to the first shift, disable location 2 shift 1 and enable location 2 shift 2
    $this->actingAs($admin)
        ->putJson('/admin/move-volunteer-to-shift', [
            'date' => $date1,
            'location_id' => $shift1->location->id,
            'shift_id' => $shift1->id,
            'old_shift_id' => $shift2->id,
            'user_id' => $movingUserId,
        ])
        ->assertSuccessful();

    $shift1->refresh();
    $shift2->refresh();
    $shift3->refresh();

    expect($shift1->getUsersOnDate($date1)->count())->toBe(3);
    expect($shift1->getUsersOnDate($date2)->count())->toBe(3);
    expect($shift2->users)->toHaveCount(2);
    expect($shift3->users)->toHaveCount(2);

    $shift2->is_enabled = false;
    $shift2->save();

    $shift3->is_enabled = true;
    $shift3->save();

    // Now, check that the volunteer will move to the third shift.
    $this->actingAs($admin)
        ->putJson('/admin/move-volunteer-to-shift', [
            'date' => $date1,
            'location_id' => $shift2->location->id,
            'shift_id' => $shift3->id,
            'old_shift_id' => $shift1->id,
            'user_id' => $movingUserId,
        ])
        ->assertSuccessful();

    $shift1->refresh();
    $shift2->refresh();
    $shift3->refresh();

    expect($shift1->getUsersOnDate($date1)->count())->toBe(2);
    expect($shift1->getUsersOnDate($date2)->count())->toBe(3);
    expect($shift2->users)->toHaveCount(2);
    expect($shift3->users)->toHaveCount(3);
});

test('move volunteer to correct shift when duplicate shifts but different days', function () {
    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);
    $user = User::factory()->enabled()->create();
    $date = '2023-01-01';

    // A Sunday
    $oldLocation = Location::factory()
        ->threeVolunteers()
        ->has(
            Shift::factory()
                ->everyDay9am()
                ->hasAttached($user, ['shift_date' => $date])
        )
        ->create();

    $location = Location::factory()
        ->allPublishers()
        ->threeVolunteers()
        ->has(Shift::factory()->weekdays9am())
        ->has(Shift::factory()->weekends9am())
        ->create();

    $this->actingAs($admin)
        ->putJson('/admin/move-volunteer-to-shift', [
            'date' => $date,
            'location_id' => $location->id,
            'shift_id' => $location->shifts[1]->id,
            'old_shift_id' => $oldLocation->shifts[0]->id,
            'user_id' => $user->id,
        ])
        ->assertSuccessful();

    $location->shifts->each->load(['users']);

    expect($oldLocation->shifts[0]->users)->toHaveCount(0);
    expect($location->shifts[0]->users)->toHaveCount(0);
    expect($location->shifts[1]->users)->toHaveCount(1);
    expect($location->shifts[1]->getUsersOnDate($date)->count())->toBe(1);
});

test('fail move volunteer to disabled shift', function () {
    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);

    $date = '2023-01-03';

    /** @var Collection<int, Location> $locations */
    $locations = collect();
    $locations[] = Location::factory()
        ->allPublishers()
        ->threeVolunteers()
        ->has(Shift::factory()
            ->everyDay9am()
            ->hasAttached(User::factory()->enabled()->count(3), ['shift_date' => $date])
        )
        ->create();

    $locations[] = Location::factory()
        ->allPublishers()
        ->threeVolunteers()
        ->has(Shift::factory()
            ->everyDay9am()
            ->state(['is_enabled' => false])
            ->hasAttached(User::factory()->enabled()->count(2), ['shift_date' => $date])
        )
        ->create();

    $locations->each->load(['shifts', 'shifts.users', 'shifts.location']);
    $shifts = $locations->map->shifts->flatten();

    // Just to be sure we have the correct number of shifts
    expect($shifts)->toHaveCount(2);
    expect($shifts[0]->users)->toHaveCount(3);
    expect($shifts[1]->users)->toHaveCount(2);

    expect($shifts[0]->is_enabled)->toBeTrue();

    // Make sure the disabled shift is not enabled
    expect($shifts[1]->is_enabled)->toBeFalse();

    $this->actingAs($admin)
        ->putJson('/admin/move-volunteer-to-shift', [
            'date' => $date,
            'location_id' => $shifts[1]->location->id,
            'shift_id' => $shifts[1]->id,
            'old_shift_id' => $shifts[0]->getKey(),
            'user_id' => $shifts[0]->users->last()->getKey(),
        ])
        ->assertUnprocessable()
        ->assertContainsStringIgnoringCase('message', 'The selected shift id is invalid');
    $shifts->each->refresh();

    expect($shifts[0]->users)->toHaveCount(3);
    expect($shifts[1]->users)->toHaveCount(2);
});
