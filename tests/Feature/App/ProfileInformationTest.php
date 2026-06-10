<?php

namespace Tests\Feature\App;

use App\Data\UserVacationData;
use App\Enums\AvailabilityHours;
use App\Http\Resources\UserVacationResource;
use App\Models\Location;
use App\Models\User;
use App\Models\UserAvailability;
use App\Models\UserVacation;
use App\Settings\GeneralSettings;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ProfileInformationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_information_can_be_updated(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->put('/user/profile-information', [
            'name'  => 'Test Name',
            'email' => 'test@example.com',
        ]);

        $this->assertEquals('Test Name', $user->fresh()->name);
        $this->assertEquals('test@example.com', $user->fresh()->email);
    }

    public function test_user_can_add_update_delete_vacations(): void
    {
        $user = User::factory()->enabled()->create();

        $vacationData = [
            'vacations' => [
                ['start_date' => '2023-01-01', 'end_date' => '2023-01-15', 'description' => 'Testing'],
                ['start_date' => '2023-02-01', 'end_date' => '2023-02-15', 'description' => 'Testing 2'],
            ],
        ];

        $this->actingAs($user)
            ->putJson("/user/vacations", $vacationData)
            ->assertRedirect("/user/availability");

        $user->refresh()->load(['vacations']);
        $this->assertCount(2, $user->vacations);
        $this->assertSame('Testing', $user->vacations[0]->description);
        $this->assertSame('Testing 2', $user->vacations[1]->description);

        $vacation              = $user->vacations[0];
        $vacation->start_date  = '2023-01-07';
        $vacation->description = 'Testing Updated';
        $this->actingAs($user)
            ->putJson("/user/vacations", [
                'vacations' => [$vacation->toArray()],
            ])
            ->assertRedirect("/user/availability");

        $user->refresh()->load(['vacations']);
        $this->assertCount(2, $user->vacations);
        $this->assertSame('2023-01-07', $user->vacations[0]->start_date);
        $this->assertSame('Testing Updated', $user->vacations[0]->description);
        $this->assertSame('Testing 2', $user->vacations[1]->description);

        $this->actingAs($user)
            ->putJson("/user/vacations", [
                'deletedVacations' => [['id' => $vacation->getKey()]],
            ])
            ->assertRedirect("/user/availability");

        $user->refresh()->load(['vacations']);
        $this->assertCount(1, $user->vacations);
        $this->assertSame('Testing 2', $user->vacations[0]->description);
    }

    public function test_admin_can_maintain_his_own_vacations(): void
    {
        $admin = User::factory()->enabled()->adminRoleUser()->create();

        $vacation1 = ['start_date' => '2023-01-01', 'end_date' => '2023-01-15', 'description' => 'Testing'];
        $vacation  = UserVacation::factory()
            ->state($vacation1)
            ->for($admin)
            ->create();
        $data      = UserVacationData::from($vacation);

        $vacationData = [
            'vacations' => [
                $data->toArray(),
                ['start_date' => '2023-02-01', 'end_date' => '2023-02-15', 'description' => 'Testing 2'],
            ],
        ];

        $this->actingAs($admin)
            ->putJson("/user/vacations", $vacationData)
            ->assertRedirect("/user/availability");

        $admin->refresh()->load(['vacations']);
        $this->assertCount(2, $admin->vacations);
        $this->assertSame('Testing', $admin->vacations[0]->description);
        $this->assertSame('Testing 2', $admin->vacations[1]->description);
    }

    public function test_user_cannot_add_vacations_for_other_users(): void
    {
        $settings                         = app()->make(GeneralSettings::class);
        $settings->enableUserAvailability = true;
        $settings->save();

        $user  = User::factory()->enabled()->create();
        $user2 = User::factory()->enabled()->create();

        $vacationData = [
            'user_id'   => $user2->id,
            'vacations' => [
                ['start_date' => '2023-01-01', 'end_date' => '2023-01-15', 'description' => 'Testing'],
                ['start_date' => '2023-02-01', 'end_date' => '2023-02-15', 'description' => 'Testing 2'],
            ],
        ];

        $this->actingAs($user)
            ->putJson("/user/vacations", $vacationData)
            ->assertInvalid([
                'user_id'
            ]);

        $this->assertDatabaseCount('user_vacations', 0);
    }

    public function test_user_cannot_update_vacations_using_another_users_vacation_id(): void
    {
        $settings                         = app()->make(GeneralSettings::class);
        $settings->enableUserAvailability = true;
        $settings->save();

        $user  = User::factory()->enabled()->create();
        $user2 = User::factory()->enabled()->create();

        $state = [
            'user_id'     => $user2->id,
            'start_date'  => '2023-01-01',
            'end_date'    => '2023-01-15',
            'description' => 'Testing',
        ];

        $vacation = UserVacation::factory()->state($state)->create();

        $vacationData = [
            'vacations' => [
                [
                    'id'          => $vacation->id, 'start_date' => '2024-01-01', 'end_date' => '2024-01-15',
                    'description' => 'Updated Testing'
                ],
            ],
        ];

        $this->actingAs($user)
            ->putJson("/user/vacations", $vacationData)
            ->assertUnprocessable();

        $found = UserVacation::find($vacation->id);
        $this->assertSame($state['start_date'], $found->start_date);
        $this->assertSame($state['end_date'], $found->end_date);
        $this->assertSame($state['description'], $found->description);
    }

    public function test_user_cannot_delete_vacations_using_another_users_vacation_id(): void
    {
        $settings                         = app()->make(GeneralSettings::class);
        $settings->enableUserAvailability = true;
        $settings->save();

        $user  = User::factory()->enabled()->create();
        $user2 = User::factory()->enabled()->create();

        $state = [
            'user_id'     => $user2->id,
            'start_date'  => '2023-01-01',
            'end_date'    => '2023-01-15',
            'description' => 'Testing',
        ];

        $vacation = UserVacation::factory()->state($state)->create();

        $vacationData = [
            'deletedVacations' => [
                ['id' => $vacation->id],
            ],
        ];
        $this->assertDatabaseCount('user_vacations', 1);

        $this->actingAs($user)
            ->putJson("/user/vacations", $vacationData)
            ->assertUnprocessable();

        $this->assertDatabaseCount('user_vacations', 1);
    }

    public function test_user_vacations_data_is_validated(): void
    {
        $settings                         = app()->make(GeneralSettings::class);
        $settings->enableUserAvailability = true;
        $settings->save();

        $user = User::factory()->enabled()->create();

        $vacationData = [
            'user_id'          => User::latest('id')->first()->getKey() + 1,
            'vacations'        => [
                ['start_date' => '2023-01-01', 'end_date' => '2023-01-15', 'description' => 'Testing'],
                ['start_date' => 'test', 'end_date' => '', 'description' => 'Testing 2'],
                ['start_date' => '2023-03-01', 'end_date' => '2023-03-15', 'description' => Str::repeat('1', 300)],
            ],
            'deletedVacations' => [
                ['id' => 5555],
            ],
        ];

        $this->actingAs($user)
            ->putJson("/user/vacations", $vacationData)
            ->assertUnprocessable()
            ->assertInvalid([
                'user_id',
                'vacations.1.start_date',
                'vacations.1.end_date',
                'vacations.2.description',
                'deletedVacations.0.id',
            ]);
    }

    public function test_user_vacations_dates_cannot_be_in_wrong_order(): void
    {
        $settings                         = app()->make(GeneralSettings::class);
        $settings->enableUserAvailability = true;
        $settings->save();

        $user = User::factory()->enabled()->create();

        $vacationData = [
            'vacations' => [
                ['start_date' => '2023-01-01', 'end_date' => '2023-01-15', 'description' => 'Valid'],
                ['start_date' => '2023-02-28', 'end_date' => '2023-02-15', 'description' => 'Invalid'],
            ],
        ];

        $this->actingAs($user)
            ->putJson("/user/vacations", $vacationData)
            ->assertUnprocessable()
            ->assertValid('vacations.0')
            ->assertInvalid([
                'vacations.1.start_date',
                'vacations.1.end_date',
            ]);
    }

    public function test_user_can_maintain_location_choices(): void
    {
        $settings                            = app()->make(GeneralSettings::class);
        $settings->enableUserLocationChoices = true;
        $settings->save();

        $user      = User::factory()->enabled()->create();
        $locations = Location::factory()
            ->count(3)
            ->sequence(fn(Sequence $sequence) => ['name' => 'Location ' . $sequence->index])
            ->create();

        $choiceData = [
            'selectedLocations' => [
                $locations[0]->getKey(),
                $locations[2]->getKey(),
            ],
        ];

        $this->actingAs($user)
            ->putJson("/user/available-locations", $choiceData)
            ->assertRedirect("/user/availability")
            ->assertSessionHas('flash.banner', "your preferred locations have been updated.");

        $user->refresh()->load(['rosterLocations']);
        $this->assertCount(2, $user->rosterLocations);
        $this->assertSame($locations[0]->name, $user->rosterLocations[0]->name);
        $this->assertSame($locations[2]->name, $user->rosterLocations[1]->name);
        $this->assertNotSame($locations[1]->name, $user->rosterLocations[0]->name, 'Verify data is not duplicated');

        $choiceData['selectedLocations'][1] = $locations[1]->getKey();

        $this->actingAs($user)
            ->putJson("/user/available-locations", $choiceData)
            ->assertRedirect("/user/availability");

        $user->refresh()->load(['rosterLocations']);
        $rosterLocations = $user->rosterLocations->pluck('name');
        $this->assertContains($locations[0]->name, $rosterLocations);
        $this->assertContains($locations[1]->name, $rosterLocations);
    }

    public function test_user_can_get_user_location_choices(): void
    {
        $settings                            = app()->make(GeneralSettings::class);
        $settings->enableUserLocationChoices = true;
        $settings->save();

        $user      = User::factory()->enabled()->create();
        $locations = Location::factory()->count(3)->create();

        $this->actingAs($user)
            ->getJson("/user/available-locations")
            ->assertOk()
            ->assertJsonCount(3)
            ->assertJsonFragment(['id' => $locations[0]->id, 'name' => $locations[0]->name])
            ->assertJsonFragment(['id' => $locations[1]->id, 'name' => $locations[1]->name])
            ->assertJsonFragment(['id' => $locations[2]->id, 'name' => $locations[2]->name]);
    }

    public function test_user_cant_maintain_disabled_feature_of_user_location_choices(): void
    {
        $user      = User::factory()->enabled()->create();
        $locations = Location::factory()->count(3)->create();

        $this->actingAs($user)
            ->getJson("/user/available-locations")
            ->assertInvalid(['featureDisabled']);

        $choiceData = [
            'selectedLocations' => [
                $locations[0]->getKey(),
                $locations[2]->getKey(),
            ],
        ];
        $this->actingAs($user)
            ->putJson("/user/available-locations", $choiceData)
            ->assertInvalid(['featureDisabled']);
    }

    public function test_non_admin_cannot_retrieve_another_users_location_choices_or_vacations(): void
    {
        $settings                            = app()->make(GeneralSettings::class);
        $settings->enableUserLocationChoices = true;
        $settings->save();

        $user      = User::factory()->enabled()->create();
        $user2     = User::factory()->enabled()->create();
        $locations = Location::factory()->count(3)->create();

        $choiceData = [
            'user_id'           => $user2->getKey(),
            'selectedLocations' => [
                $locations[0]->getKey(),
                $locations[2]->getKey(),
            ],
        ];

        $this->actingAs($user)
            ->putJson("/user/available-locations", $choiceData)
            ->assertUnauthorized();
    }

    public function test_user_cant_maintain_disabled_feature_of_user_regular_availability(): void
    {
        $user = User::factory()->enabled()->create();

        GeneralSettings::fake(['enableUserAvailability' => false]);

        $this->actingAs($user)
            ->getJson("/user/availability")
            ->assertInvalid(['featureDisabled']);

        $availabilityData = [
            "day_monday"     => [7, 18],
            "day_tuesday"    => [12, 13, 14, 15, 16, 17, 18],
            "day_wednesday"  => [7, 18],
            "day_thursday"   => [7, 18],
            "day_friday"     => [7, 18],
            "day_saturday"   => [7, 18],
            "day_sunday"     => [12, 13, 14, 15, 16, 17, 18],
            "num_mondays"    => 0,
            "num_tuesdays"   => 4,
            "num_wednesdays" => 0,
            "num_thursdays"  => 0,
            "num_fridays"    => 0,
            "num_saturdays"  => 0,
            "num_sundays"    => 1,
            "comments"       => "Atque voluptatem debitis culpa. test"
        ];

        $this->actingAs($user)
            ->putJson("/user/availability", $availabilityData)
            ->assertInvalid(['featureDisabled']);
    }

    public function test_user_can_see_their_regular_availability_if_never_used(): void
    {
        $settings                         = app()->make(GeneralSettings::class);
        $settings->enableUserAvailability = true;
        $settings->save();

        /** @var \App\Models\User $user */
        $user = User::factory()->enabled()->create();

        $this->actingAs($user)
            ->get("/user/availability")
            ->assertInertia(fn(AssertableInertia $page) => $page
                ->component('Profile/ShowAvailability')
                ->has('availability', fn(AssertableInertia $data) => $data
                    ->where('day_monday', null)
                    ->where('day_tuesday', null)
                    ->where('day_wednesday', null)
                    ->where('day_thursday', null)
                    ->where('day_friday', null)
                    ->where('day_saturday', null)
                    ->where('day_sunday', null)
                    ->where('num_mondays', 0)
                    ->where('num_tuesdays', 0)
                    ->where('num_wednesdays', 0)
                    ->where('num_thursdays', 0)
                    ->where('num_fridays', 0)
                    ->where('num_saturdays', 0)
                    ->where('num_sundays', 0)
                    ->where('comments', null)
                )
            );
    }

    public function test_user_can_see_their_saved_regular_availability(): void
    {
        $settings                         = app()->make(GeneralSettings::class);
        $settings->enableUserAvailability = true;
        $settings->save();

        /** @var \App\Models\User $user */
        $user = User::factory()->enabled()->create();

        $availability                 = new UserAvailability(['user_id' => $user->id]);
        $availability->num_wednesdays = 2;
        $availability->day_wednesday  = [7, 18];
        $availability->comments       = "Testing";

        $user->availability = $availability;
        $user->availability->save();

        $user->load('availability');

        $this->actingAs($user)
            ->get("/user/availability")
            ->assertInertia(fn(AssertableInertia $page) => $page
                ->component('Profile/ShowAvailability')
                ->has('availability', fn(AssertableInertia $data) => $data
                    ->where('day_monday', null)
                    ->where('day_tuesday', null)
                    ->where('day_wednesday.0', 7)
                    ->where('day_wednesday.1', 18)
                    ->where('day_thursday', null)
                    ->where('day_friday', null)
                    ->where('day_saturday', null)
                    ->where('day_sunday', null)
                    ->where('num_mondays', 0)
                    ->where('num_tuesdays', 0)
                    ->where('num_wednesdays', 2)
                    ->where('num_thursdays', 0)
                    ->where('num_fridays', 0)
                    ->where('num_saturdays', 0)
                    ->where('num_sundays', 0)
                    ->where('comments', 'Testing')
                )
            );
    }

    public function test_user_can_update_their_regular_availability(): void
    {
        $settings                         = app()->make(GeneralSettings::class);
        $settings->enableUserAvailability = true;
        $settings->save();

        /** @var \App\Models\User $user */
        $user = User::factory()->enabled()->create();

        $availability                 = new UserAvailability(['user_id' => $user->id]);
        $availability->user_id        = $user->id;
        $availability->num_wednesdays = 2;
        $availability->day_wednesday  = [7, 18];
        $availability->comments       = "Testing";
        $user->availability           = $availability;
        $user->availability->save();

        $this->actingAs($user)
            ->putJson("/user/availability", [
                'day_monday'     => null,
                'day_tuesday'    => null,
                'day_wednesday'  => [10, 13],
                'day_thursday'   => [10, 13],
                'day_friday'     => null,
                'day_saturday'   => null,
                'day_sunday'     => null,
                'num_mondays'    => 0,
                'num_tuesdays'   => 0,
                'num_wednesdays' => 1,
                'num_thursdays'  => 1,
                'num_fridays'    => 0,
                'num_saturdays'  => 0,
                'num_sundays'    => 0,
                'comments'       => "Testing 123",
            ])
            ->assertRedirect("/user/availability");

        $availability->refresh();
        $this->assertSame(1, $availability->num_wednesdays);
        $this->assertSame(1, $availability->num_thursdays);
        $this->assertCount(4, $availability->day_wednesday);
        $this->assertCount(4, $availability->day_thursday);
        $this->assertEquals(AvailabilityHours::Ten, $availability->day_wednesday[0]);
        $this->assertEquals(AvailabilityHours::Thirteen, $availability->day_wednesday[3]);
        $this->assertEquals(AvailabilityHours::Ten, $availability->day_thursday[0]);
        $this->assertEquals(AvailabilityHours::Thirteen, $availability->day_thursday[3]);
        $this->assertEquals("Testing 123", $availability->comments);
    }

    /**
     * Technically, the user doesn't 'create' but when they perform an update and there is no availability record,
     * the record should be created. This test is to ensure that the record is created.
     */
    public function test_user_can_create_their_regular_availability(): void
    {
        $settings                         = app()->make(GeneralSettings::class);
        $settings->enableUserAvailability = true;
        $settings->save();

        /** @var \App\Models\User $user */
        $user = User::factory()->enabled()->create();

        $this->actingAs($user)
            ->putJson("/user/availability", [
                'day_wednesday'  => [10, 13],
                'day_thursday'   => [10, 13],
                'num_mondays'    => 0,
                'num_tuesdays'   => 0,
                'num_wednesdays' => 1,
                'num_thursdays'  => 1,
                'num_fridays'    => 0,
                'num_saturdays'  => 0,
                'num_sundays'    => 0,
                'comments'       => "Testing 123",
            ])
            ->assertRedirect("/user/availability");

        $availability = UserAvailability::firstwhere('user_id', $user->id);
        $this->assertSame(1, $availability->num_wednesdays);
        $this->assertSame(1, $availability->num_thursdays);
        $this->assertCount(4, $availability->day_wednesday);
        $this->assertCount(4, $availability->day_thursday);
        $this->assertEquals(AvailabilityHours::Ten, $availability->day_wednesday[0]);
        $this->assertEquals(AvailabilityHours::Thirteen, $availability->day_wednesday[3]);
        $this->assertEquals(AvailabilityHours::Ten, $availability->day_thursday[0]);
        $this->assertEquals(AvailabilityHours::Thirteen, $availability->day_thursday[3]);
        $this->assertEquals("Testing 123", $availability->comments);
    }

    public function test_user_regular_availability_data_is_validated(): void
    {
        $settings                         = app()->make(GeneralSettings::class);
        $settings->enableUserAvailability = true;
        $settings->save();

        /** @var \App\Models\User $user */
        $user = User::factory()->enabled()->create();
        $this->actingAs($user)
            ->putJson("/user/availability", [
                'day_wednesday'  => [13], // minimum of 2 values
                'day_thursday'   => [], // required
                'day_friday'     => [18, 24],
                'day_saturday'   => [12, 16], // valid but should be ignored because of num_saturdays
                'num_mondays'    => false, // invalid value
                // tuesday is missing
                'num_wednesdays' => 1, // valid value but no corresponding day value
                'num_thursdays'  => 5, // no more than 4 thursdays
                'num_fridays'    => 1,
                'num_saturdays'  => 0,
                'num_sundays'    => 'test', // invalid value
                'comments'       => Str::repeat('1', 600), // too long
            ])
            ->assertUnprocessable()
            ->assertInvalid([
                'day_wednesday' => 'This field must have at least 2 items',
                'day_friday.6'  => 'The day_friday.6 field must not be greater than 23.',
                'num_mondays'   => 'The num mondays field must be an integer.',
                'num_tuesdays'  => 'The num tuesdays field is required.',
                'num_thursdays' => 'The num thursdays field must not be greater than 4.',
                'num_sundays'   => 'The num sundays field must be an integer.',
                'comments'      => 'The comments field must not be greater than 500 characters.',
            ]);
    }

    public function test_user_cannot_maintain_availability_for_other_users(): void
    {
        $settings                         = app()->make(GeneralSettings::class);
        $settings->enableUserAvailability = true;
        $settings->save();

        $user  = User::factory()->enabled()->create();
        $user2 = User::factory()->enabled()->create();

        $this->actingAs($user)
            ->putJson("/user/availability", [
                'user_id'        => $user2->id,
                'day_wednesday'  => [10, 13],
                'day_thursday'   => [10, 13],
                'num_mondays'    => 0,
                'num_tuesdays'   => 0,
                'num_wednesdays' => 1,
                'num_thursdays'  => 1,
                'num_fridays'    => 0,
                'num_saturdays'  => 0,
                'num_sundays'    => 0,
                'comments'       => "Testing 123",
            ])
            ->assertUnauthorized();

        $this->assertDatabaseCount('user_availabilities', 0);
    }

    public function test_user_is_prompted_to_update_availability(): void
    {
        $settings                         = app()->make(GeneralSettings::class);
        $settings->enableUserAvailability = true;
        $settings->save();

        $user = User::factory()->enabled()->create();

        // First confirm that the user is prompted to update their availability
        $this->actingAs($user)
            ->get("/")
            ->assertInertia(fn(AssertableInertia $page) => $page
                ->component('Dashboard')
                ->where('needsToUpdateAvailability', true)
            )
            ->assertSuccessful();

        $user->load('availability');
        // Then user should view their availability
        $this->actingAs($user)
            ->get("/user/availability")
            ->assertInertia(fn(AssertableInertia $page) => $page
                ->component('Profile/ShowAvailability')
                ->where('needsToUpdateAvailability', true)
            )
            ->assertSuccessful();

        // Travel to a future date before 'flagging' the user for having updated their availability. This will ensure
        // that the user isn't prompted to update their availability after 'viewing' it.
        $this->travelTo(Carbon::now()->addDay());
        // This should happen automatically when navigating to /user/availability but will only work if the user has navigated to /user/availability
        $this->actingAs($user)
            ->postJson("/set-viewed-availability")
            ->assertSuccessful()
            ->assertContent('');

        $this->actingAs($user)
            ->get("/")
            ->assertInertia(fn(AssertableInertia $page) => $page
                ->component('Dashboard')
                ->whereNot('needsToUpdateAvailability', true)
            )
            ->assertSuccessful();
    }
}
