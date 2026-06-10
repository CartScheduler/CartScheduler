<?php

namespace Tests\Feature\App;

use App\Actions\ErrorApiResource;
use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestrictedVolunteerTest extends TestCase
{
    use RefreshDatabase;

    public function test_restricted_user_cannot_reserve(): void
    {
        $user = User::factory()->enabled()->state(['is_unrestricted' => false])->create();

        $startDate = CarbonImmutable::createFromTimeString('2023-01-15 12:00:00');

        $this->travelTo($startDate);
        $location = Location::factory()
            ->state(['max_volunteers' => 3])
            ->has(Shift::factory()->everyDay9am())
            ->create();

        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => true,
            'date'       => $startDate->addDay()->toDateString(),
        ])
            ->assertJsonPath('error_code', ErrorApiResource::CODE_NOT_ALLOWED)
            ->assertUnprocessable();

        $this->assertDatabaseEmpty('shift_user');
    }

    public function test_user_cannot_release(): void
    {
        $user = User::factory()->enabled()->state(['is_unrestricted' => false])->create();

        $startDate = CarbonImmutable::createFromTimeString('2023-01-15 12:00:00');

        $this->travelTo($startDate);
        /** @var Location $location */
        $location = Location::factory()
            ->state(['requires_brother' => true, 'max_volunteers' => 3])
            ->has(
                Shift::factory()
                    ->everyDay9am()
                    ->hasAttached($user, ['shift_date' => $startDate->addDay()->toDateString()])
            )
            ->create();

        $this->actingAs($user)->postJson('/reserve-shift', [
            'location'   => $location->id,
            'shift'      => $location->shifts[0]->id,
            'do_reserve' => false,
            'date'       => $startDate->addDay()->toDateString(),
        ])
            ->assertJsonPath('error_code', ErrorApiResource::CODE_NOT_ALLOWED)
            ->assertUnprocessable();

        $this->assertDatabaseCount('shift_user', 1);
    }

    public function test_restricted_user_cannot_see_shifts_outside_of_their_own_roster(): void
    {
        $users = User::factory()
            ->enabled()
            ->count(4)
            ->sequence(['is_unrestricted' => false], ['is_unrestricted' => true], ['is_unrestricted' => true],
                ['is_unrestricted' => true])
            ->create();

        $date  = '2023-01-03'; // A Tuesday
        $date2 = '2023-01-04';
        Location::factory()
            ->state(['name' => 'Location 1'])
            ->has(
                Shift::factory()
                    ->hasAttached($users, ['shift_date' => $date])
                    ->everyDay9am()
            )
            ->create();

        Location::factory()
            ->state(['name' => 'Location 2'])
            ->has(
                Shift::factory()
                    ->hasAttached(
                        User::factory()->enabled()->count(2),
                        ['shift_date' => $date]
                    )
                    ->hasAttached(
                        User::factory()->enabled()->count(2),
                        ['shift_date' => $date2]
                    )
                    ->everyDay9am()
            )
            ->create();

        $this->travelTo('2023-01-02 09:00:00');

        $this->assertDatabaseCount('shift_user', 8);

        $this->actingAs($users[0])
            ->getJson("/shifts/$date")
            ->assertOk()
            ->assertJsonCount(1, 'locations') // One of the two locations should be filtered out
            ->assertJsonCount($users->count(), 'locations.0.shifts.0.volunteers')
            ->assertJsonMissingPath('locations.1');

        $this->actingAs($users[0])
            ->getJson("/shifts/$date2")
            ->assertOk()
            ->assertJsonCount(0, 'locations') // No locations should be visible on this date
            ->assertJsonMissingPath('locations.0')
            ->assertJsonMissingPath('locations.1');

        // Confirm that unrestricted users can see other shifts
        $this->actingAs($users[1])
            ->getJson("/shifts/$date")
            ->assertOk()
            ->assertJsonCount(2, 'locations')
            ->assertJsonCount($users->count(), 'locations.0.shifts.0.volunteers');

        $this->actingAs($users[1])
            ->getJson("/shifts/$date2")
            ->assertOk()
            // Next day, same location different time
            ->assertJsonCount(0, 'locations.0.shifts.0.volunteers')
            ->assertJsonCount(2, 'locations.1.shifts.0.volunteers')
            ->assertJsonHasKeys('locations.1.shifts.0.volunteers.0', 'name')
            ->assertJsonHasKeys('locations.1.shifts.0.volunteers.1', 'name');
    }
}
