<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature\App;

use App\Enums\DBPeriod;
use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\SetConfig;

class ReservationsTest extends TestCase
{
    use RefreshDatabase;
    use SetConfig;

    public function test_female_user_can_reserve_and_release(): void
    {
        $user = User::factory()->female()->create();

        $startDate = CarbonImmutable::createFromTimeString('2023-01-15 12:00:00');

        $this->travelTo($startDate);
        $location = Location::factory()
            ->allPublishers()
            ->has(Shift::factory()->everyDay9am())
            ->create();

        $this->assertDatabaseCount('shift_user', 0);

        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->addDay()->toDateString(),
        ])->assertOk();

        $this->assertDatabaseCount('shift_user', 1);
        $this->assertDatabaseHas('shift_user', [
            'shift_id'   => $location->shifts[0]->id,
            'user_id'    => $user->getKey(),
            'shift_date' => $startDate->addDay()->toDateString(),
        ]);

        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => false,
            'date'       => $startDate->addDay()->toDateString(),
        ])
            ->assertOk();

        $this->assertDatabaseCount('shift_user', 0);
        $this->assertDatabaseMissing('shift_user', [
            'shift_id'   => $location->shifts[0]->id,
            'user_id'    => $user->getKey(),
            'shift_date' => $startDate->addDay()->toDateString(),
        ]);
    }

    public function test_female_user_cannot_reserve_last_spot_of_shift_requiring_one_brother(): void
    {
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
                            ->count(2)
                        , ['shift_date' => $startDate->addDay()->toDateString()]
                    )
            )
            ->create();

        $response = $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->addDay()->toDateString(),
        ])
            ->assertUnprocessable()
            ->assertInvalid('shift');

        $this->assertStringContainsStringIgnoringCase('needs to be a brother', $response->json('errors.shift.0'));
        $this->assertDatabaseCount('shift_user', 2);
    }

    public function test_male_user_can_reserve_and_release(): void
    {
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
                            ->count(2)
                        , ['shift_date' => $startDate->addDay()->toDateString()]
                    )
            )
            ->create();

        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->addDay()->toDateString(),
        ])->assertOk();

        $this->assertDatabaseCount('shift_user', 3);
        $this->assertDatabaseHas('shift_user', [
            'shift_id'   => $location->shifts[0]->id,
            'user_id'    => $user->getKey(),
            'shift_date' => $startDate->addDay()->toDateString(),
        ]);

        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => false,
            'date'       => $startDate->addDay()->toDateString(),
        ])->assertOk();

        $this->assertDatabaseCount('shift_user', 2);
        $this->assertDatabaseMissing('shift_user', [
            'shift_id'   => $location->shifts[0]->id,
            'user_id'    => $user->getKey(),
            'shift_date' => $startDate->addDay()->toDateString(),
        ]);
    }

    public function test_not_enabled_user_cannot_reserve_shifts(): void
    {
        $user = User::factory()->state(['is_enabled' => false])->male()->create();

        $startDate = CarbonImmutable::createFromTimeString('2023-01-15 12:00:00');

        $this->travelTo($startDate);
        $location = Location::factory()
            ->threeVolunteers()
            ->has(Shift::factory()->everyDay9am())
            ->create();

        $this->assertDatabaseCount('shift_user', 0);

        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->addDay()->toDateString(),
        ])->assertUnauthorized();

        $this->assertDatabaseCount('shift_user', 0);
    }

    public function test_user_can_reserve_on_the_first_day_of_reservation_period(): void
    {
        $user = User::factory()->male()->create();

        $startDate = CarbonImmutable::createFromTimeString('2023-01-15 00:00:01');

        $this->travelTo($startDate);
        $location = Location::factory()
            ->has(Shift::factory()->everyDay9am())
            ->create();

        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->toDateString(),
        ])->assertOk();

        $this->assertDatabaseCount('shift_user', 1);
        $this->assertDatabaseHas('shift_user', [
            'shift_id'   => $location->shifts[0]->id,
            'user_id'    => $user->getKey(),
            'shift_date' => $startDate->toDateString(),
        ]);
    }

    public function test_user_can_reserve_last_day_of_reservation_period(): void
    {
        $this->setConfig(1, DBPeriod::Week, false, 'MON', '00:00');

        $user = User::factory()->male()->create();

        $startDate = CarbonImmutable::createFromTimeString('2023-01-11 00:00:01'); // Wednesday

        $this->travelTo($startDate);
        $location = Location::factory()
            ->has(Shift::factory()->everyDay9am())
            ->create();

        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->setDay(15)->toDateString(), // set to the last day which is a Sunday
        ])->assertOk();

        $this->assertDatabaseCount('shift_user', 1);
        $this->assertDatabaseHas('shift_user', [
            'shift_id'   => $location->shifts[0]->id,
            'user_id'    => $user->getKey(),
            'shift_date' => $startDate->setDay(15)->toDateString(),
        ]);
    }

    public function test_user_cannot_reserve_day_before_today(): void
    {
        $user = User::factory()->male()->create();

        $startDate = CarbonImmutable::createFromTimeString('2023-01-11 00:00:01'); // Wednesday

        $this->travelTo($startDate);
        $location = Location::factory()
            ->has(Shift::factory()->everyDay9am())
            ->create();

        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->subDay()->toDateString(),
        ])->assertInvalid('date');
        $this->assertDatabaseCount('shift_user', 0);
    }

    public function test_user_cannot_reserve_day_after_last_day_of_reservation_period(): void
    {
        $this->setConfig(1, DBPeriod::Week, true, 'SUN', '00:00');

        $user = User::factory()->male()->create();

        $startDate = CarbonImmutable::createFromTimeString('2023-01-11 00:00:01'); // Wednesday

        $this->travelTo($startDate);
        $location = Location::factory()
            ->has(Shift::factory()->everyDay9am())
            ->create();

        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->addWeek()->toDateString(),
        ])
            ->assertUnprocessable()
            ->assertInvalid('date');
        $this->assertDatabaseCount('shift_user', 0);
    }

    /**
     * Make sure a volunteer can't attach themselves to a shift that already maxed out with volunteers
     */
    public function test_user_cannot_reserve_full_shift(): void
    {
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
                            ->count(3)
                        , ['shift_date' => $startDate->addDay()->toDateString()]
                    )
            )
            ->create();

        $response = $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->addDay()->toDateString(),
        ])->assertUnprocessable();

        $this->assertSame(100, $response->json('error_code'));
        $this->assertDatabaseCount('shift_user', 3);
    }

    public function test_user_cannot_reserve_already_reserved_shift(): void
    {
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
                        $user
                        , ['shift_date' => $startDate->addDay()->toDateString()]
                    )
            )
            ->create();

        $this->assertDatabaseCount('shift_user', 1);

        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->addDay()->toDateString(),
        ])
            ->assertUnprocessable()
            ->assertInvalid('shift');

        $this->assertDatabaseCount('shift_user', 1);
    }

    public function test_male_user_can_reserve_shift_that_does_not_require_male_with_only_females_occupying(): void
    {
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
                            ->count(2)
                        , ['shift_date' => $startDate->addDay()->toDateString()]
                    )
            )
            ->create();

        $this->assertDatabaseCount('shift_user', 2);

        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->addDay()->toDateString(),
        ])
            ->assertOk();

        $this->assertDatabaseCount('shift_user', 3);
    }

    public function test_male_user_can_reserve_shift_that_does_require_male_with_only_females_occupying(): void
    {
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
                            ->count(2)
                        , ['shift_date' => $startDate->addDay()->toDateString()]
                    )
            )
            ->create();

        $this->assertDatabaseCount('shift_user', 2);

        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->addDay()->toDateString(),
        ])
            ->assertOk();

        $this->assertDatabaseCount('shift_user', 3);
    }

    public function test_male_user_can_reserve_shift_that_does_require_male_with_only_males_occupying(): void
    {
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
                            ->count(2)
                        , ['shift_date' => $startDate->addDay()->toDateString()]
                    )
            )
            ->create();

        $this->assertDatabaseCount('shift_user', 2);

        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->addDay()->toDateString(),
        ])
            ->assertOk();

        $this->assertDatabaseCount('shift_user', 3);
    }

    public function test_female_user_can_reserve_shift_that_does_not_require_male_with_only_females_occupying(): void
    {
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
                            ->count(2)
                        , ['shift_date' => $startDate->addDay()->toDateString()]
                    )
            )
            ->create();

        $this->assertDatabaseCount('shift_user', 2);

        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->addDay()->toDateString(),
        ])->assertOk();

        $this->assertDatabaseCount('shift_user', 3);
    }

    public function test_female_user_cannot_reserve_shift_that_requires_brother_with_only_females_occupying(): void
    {
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
                            ->count(2)
                        , ['shift_date' => $startDate->addDay()->toDateString()]
                    )
            )
            ->create();

        $this->assertDatabaseCount('shift_user', 2);

        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->addDay()->toDateString(),
        ])
            ->assertUnprocessable()
            ->assertInvalid('shift');

        $this->assertDatabaseCount('shift_user', 2);
    }

    public function test_user_cannot_reserve_at_inactive_location(): void
    {
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
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->addDay()->toDateString(),
        ])
            ->assertUnprocessable()
            ->assertInvalid('location');

        $this->assertDatabaseCount('shift_user', 0);
    }

    public function test_user_cannot_reserve_a_disabled_shift(): void
    {
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
                'location'   => $location->id,
                'shift'      => $location->shifts[0]->id,
                'do_reserve' => true,
                'date'       => $startDate->addDay()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertInvalid('shift');

        $this->assertDatabaseCount('shift_user', 0);
    }

    public function test_user_cannot_reserve_an_overlapping_shift(): void
    {
        $user = User::factory()->male()->create();

        $startDate = CarbonImmutable::createFromTimeString('2023-01-15 12:00:00');
        $nextDay   = $startDate->addDay()->toDateString();

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
            'location'   => $location->id,
            'shift'      => $secondShift->id,
            'do_reserve' => true,
            'date'       => $nextDay,
        ])
            ->assertUnprocessable()
            ->assertInvalid('shift')
            ->assertContainsStringIgnoringCase('message', "you're already assigned")
            ->assertContainsStringIgnoringCase(
                'message',
                $location->name . ' - ' . $firstShift->start_time . ' and ' . $firstShift->end_time
            );

        $this->assertDatabaseCount('shift_user', 1);
    }

    public function test_user_cannot_reserve_a_shift_on_day_where_shift_is_not_enabled(): void
    {
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
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->addDay()->toDateString(),
        ])
            ->assertUnprocessable()
            ->assertInvalid('shift');

        $this->assertDatabaseCount('shift_user', 0);
    }

    public function test_user_cannot_see_inactive_locations(): void
    {
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
            ->assertJsonPath("freeShifts.{$startDate->toDateString()}.volunteer_count", fn(int $val) => $val === 0)
            ->assertJsonPath("freeShifts.{$startDate->toDateString()}.max_allowed", fn(int $val) => $val === 6)
            ->assertJsonPath("freeShifts.{$startDate->addDay()->toDateString()}.volunteer_count",
                fn(int $val) => $val === 4)
            ->assertJsonPath("freeShifts.{$startDate->addDay()->toDateString()}.max_allowed",
                fn(int $val) => $val === 6);

        // Disable 1 location. The assertions should halve...
        $locations[0]->is_enabled = false;
        $locations[0]->save();

        $this->actingAs($user)->getJson("/shifts/{$startDate->addDay()->toDateString()}")
            ->assertOk()
            ->assertJsonCount(1, 'locations')
            ->assertJsonCount(7, 'freeShifts')
            ->assertJsonPath("freeShifts.{$startDate->toDateString()}.volunteer_count", fn(int $val) => $val === 0)
            ->assertJsonPath("freeShifts.{$startDate->toDateString()}.max_allowed", fn(int $val) => $val === 3)
            ->assertJsonPath("freeShifts.{$startDate->addDay()->toDateString()}.volunteer_count",
                fn(int $val) => $val === 2)
            ->assertJsonPath("freeShifts.{$startDate->addDay()->toDateString()}.max_allowed",
                fn(int $val) => $val === 3);
    }

    /**
     * This test is a result of a unique bug where the following would happen:
     * - A shift was available on limited days (e.g. Saturday and Sunday)
     * - 'today' was not one of those days (e.g. Monday)
     * - The user selected the last day of the period (e.g. June 30th - a Sunday)
     * - The user would not see the location
     * This would happen because the system wasn't comparing dates correctly
     */
    public function test_user_can_see_locations_that_are_only_displayed_on_limited_days_of_the_week_on_last_day_of_period(
    ): void
    {
        $this->setConfig(1, DBPeriod::Month, false, 'MON', '12:00');

        $user = User::factory()->enabled()->create();

        $startDate = CarbonImmutable::createFromTimeString('2024-05-22 7:00:00');

        $locations = Location::factory()
            ->threeVolunteers()
            ->count(3)
            ->has(Shift::factory()->everyDay9am())
            ->has(Shift::factory()->everyDay1230pm())
            ->create();

        $locations->last()->shifts->each(fn(Shift $shift) => $shift->setRawAttributes([
            'day_monday'    => false,
            'day_tuesday'   => false,
            'day_wednesday' => false,
            'day_thursday'  => false,
            'day_friday'    => false,
            'day_saturday'  => true,
            'day_sunday'    => true,
            'available_to'  => '2024-07-31',
        ])->save());

        $sortedLocations = $locations->sortBy('name')->values();

        $this->travelTo($startDate);

        $this->actingAs($user)
            ->getJson("/shifts/2024-06-30") // previously, this date would not return all locations
            ->assertOk()
            ->assertJsonCount(3, 'locations')
            ->assertJsonPath("locations.0.name", $sortedLocations[0]->name)
            ->assertJsonPath("locations.1.name", $sortedLocations[1]->name)
            ->assertJsonPath("locations.2.name", $sortedLocations[2]->name)
            ->assertJsonCount(2, "locations.0.shifts")
            ->assertJsonCount(2, "locations.1.shifts")
            ->assertJsonCount(2, "locations.2.shifts");
    }

    public function test_user_cannot_see_disabled_shifts(): void
    {
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
            ->assertJsonPath("freeShifts.{$startDate->toDateString()}.volunteer_count", fn(int $val) => $val === 0)
            ->assertJsonPath("freeShifts.{$startDate->toDateString()}.max_allowed", fn(int $val) => $val === 6)
            ->assertJsonPath("freeShifts.{$startDate->addDay()->toDateString()}.volunteer_count",
                fn(int $val) => $val === 4)
            ->assertJsonPath("freeShifts.{$startDate->addDay()->toDateString()}.max_allowed",
                fn(int $val) => $val === 6);

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
            ->assertJsonPath("freeShifts.{$startDate->toDateString()}.volunteer_count", fn(int $val) => $val === 0)
            ->assertJsonPath("freeShifts.{$startDate->toDateString()}.max_allowed", fn(int $val) => $val === 3)
            ->assertJsonPath("freeShifts.{$startDate->addDay()->toDateString()}.volunteer_count",
                fn(int $val) => $val === 2)
            ->assertJsonPath("freeShifts.{$startDate->addDay()->toDateString()}.max_allowed",
                fn(int $val) => $val === 3);
    }

    public function test_user_cannot_reserve_daily_released_shifts_beyond_month(): void
    {
        $this->setConfig(1, DBPeriod::Month, true, null, '00:00');

        $user = User::factory()->male()->create();

        $startDate = CarbonImmutable::createFromTimeString('2023-02-08 00:00:00');

        $this->travelTo($startDate);

        $location = Location::factory()
            ->threeVolunteers()
            ->has(Shift::factory()->everyDay9am())
            ->create();

        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->addMonth()->subDay()->toDateString(),
        ])
            ->assertOk()
            ->assertContent('Reservation made');

        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->addMonth()->toDateString(),
        ])
            ->assertUnprocessable()
            ->assertInvalid('date');
    }

    public function test_user_cannot_reserve_daily_released_at_time_shifts_beyond_month(): void
    {
        $this->setConfig(1, DBPeriod::Month, true, null, '12:00');

        $user = User::factory()->male()->create();

        $startDate = CarbonImmutable::createFromTimeString('2023-02-08 00:00:00');

        $location = Location::factory()
            ->threeVolunteers()
            ->has(Shift::factory()->everyDay9am())
            ->create();

        $this->travelTo($startDate);
        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->addMonth()->subDay()->toDateString(),
        ])
            ->assertUnprocessable()
            ->assertInvalid('date');

        $this->travelTo($startDate->midDay());
        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->addMonth()->subDay()->toDateString(),
        ])
            ->assertOk()
            ->assertContent('Reservation made');
    }

    public function test_user_cannot_reserve_daily_released_shifts_beyond_week(): void
    {
        $this->setConfig(1, DBPeriod::Week, true, null, '00:00');

        $user = User::factory()->male()->create();

        $startDate = CarbonImmutable::createFromTimeString('2023-02-16 00:00:00');

        $location = Location::factory()
            ->threeVolunteers()
            ->has(Shift::factory()->everyDay9am())
            ->create();

        $this->travelTo($startDate);
        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->addWeek()->toDateString(),
        ])
            ->assertUnprocessable()
            ->assertInvalid('date');

        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->addWeek()->subDay()->toDateString(),
        ])
            ->assertOk()
            ->assertContent('Reservation made');
    }

    public function test_user_cannot_reserve_daily_released_at_time_shifts_beyond_week(): void
    {
        $this->setConfig(1, DBPeriod::Week, true, null, '12:00');

        $user = User::factory()->male()->create();

        $startDate = CarbonImmutable::createFromTimeString('2023-02-16 00:00:00');

        $location = Location::factory()
            ->threeVolunteers()
            ->has(Shift::factory()->everyDay9am())
            ->create();

        $this->travelTo($startDate);
        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->addWeek()->subDay()->toDateString(),
        ])
            ->assertUnprocessable()
            ->assertInvalid('date');

        $this->travelTo($startDate->midDay());
        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->addWeek()->subDay()->toDateString(),
        ])
            ->assertOk()
            ->assertContent('Reservation made');
    }

    public function test_user_cannot_reserve_period_shifts_released_shifts_beyond_allowed_time_with_monthly_release(
    ): void
    {
        $this->setConfig(1, DBPeriod::Month, false, null, '00:00');

        $user = User::factory()->male()->create();

        $startDate = CarbonImmutable::createFromTimeString('2023-02-08 00:00:00');

        $location = Location::factory()
            ->threeVolunteers()
            ->has(Shift::factory()->everyDay9am())
            ->create();

        $this->travelTo($startDate);
        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->addMonths(2)->firstOfMonth()->toDateString(),
        ])
            ->assertUnprocessable()
            ->assertInvalid('date');

        $this->travelTo($startDate->midDay());
        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            // Used the syntax below to be a more obvious comparison to the above.
            'date'       => $startDate->addMonths(2)->firstOfMonth()->subDay()->toDateString(),
        ])
            ->assertOk()
            ->assertContent('Reservation made');
    }

    public function test_user_cannot_reserve_released_shifts_beyond_allowed_time_with_weekly_release(): void
    {
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
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->setDay(19)->toDateString(),
        ])
            ->assertUnprocessable()
            ->assertInvalid('date');

        $this->travelTo($startDate->midDay());
        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->setDay(18)->toDateString(),
        ])
            ->assertOk()
            ->assertContent('Reservation made');
    }

    public function test_user_cannot_reserve_new_shifts_before_allowed_time(): void
    {
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
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->setDay(26)->toDateString(),
        ])
            ->assertUnprocessable()
            ->assertInvalid('date');

        $this->travelTo($startDate->midDay()->addMinutes(30));
        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->setDay(26)->toDateString(),
        ])
            ->assertOk()
            ->assertContent('Reservation made');
    }

    public function test_user_can_see_shifts_and_can_only_access_approved_data(): void
    {
        $users = User::factory()->enabled()->count(4)->create();
        $date  = '2023-01-03'; // A Tuesday
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
                'name' => $users[0]->name, 'mobile_phone' => $users[0]->mobile_phone, 'uuid' => $users[0]->uuid
            ])
            ->assertJsonFragment(['name' => $users[1]->name, 'mobile_phone' => $users[1]->mobile_phone])
            ->assertJsonFragment(['name' => $users[2]->name, 'mobile_phone' => $users[2]->mobile_phone])
            ->assertJsonFragment(['name' => $users[3]->name, 'mobile_phone' => $users[3]->mobile_phone])
            // Make sure we're not leaking non-needed data
            ->tap(fn(TestResponse $response) => $response->assertExactJsonStructure(
                structure: [
                    '*' => [
                        'name',
                        'uuid',
                        'gender',
                        'mobile_phone'
                    ]
                ],
                responseData: $response->json('locations.0.shifts.0.volunteers'),
            ))
            ->assertJsonMissingPath('locations.0.shifts.0.volunteers.1.id')
            ->assertJsonMissingPath('locations.0.shifts.0.volunteers.2.id');
    }

    public function test_remove_volunteer_when_not_attached_to_shift(): void
    {
        $location = Location::factory()
            ->threeVolunteers()
            ->allPublishers()
            ->has(Shift::factory()->everyDay9am())
            ->create();

        $user = User::factory()->enabled()->create();

        $this->travelTo('2023-01-02 09:00:00');
        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => false, // setting this to false should fail
            'date'       => '2023-01-03',
        ])
            ->assertUnprocessable()
            ->assertInvalid('shift');
    }
}
