<?php

use App\Data\UserVacationData;
use App\Enums\AvailabilityHours;
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

uses(RefreshDatabase::class);

test('profile information can be updated', function () {
    $this->actingAs($user = User::factory()->create());

    $this->put('/user/profile-information', [
        'name' => 'Test Name',
        'email' => 'test@example.com',
    ]);

    expect($user->fresh()->name)->toEqual('Test Name');
    expect($user->fresh()->email)->toEqual('test@example.com');
});

test('user can add update delete vacations', function () {
    $user = User::factory()->enabled()->create();

    $vacationData = [
        'vacations' => [
            ['start_date' => '2023-01-01', 'end_date' => '2023-01-15', 'description' => 'Testing'],
            ['start_date' => '2023-02-01', 'end_date' => '2023-02-15', 'description' => 'Testing 2'],
        ],
    ];

    $this->actingAs($user)
        ->putJson('/user/vacations', $vacationData)
        ->assertRedirect('/user/availability');

    $user->refresh()->load(['vacations']);
    expect($user->vacations)->toHaveCount(2);
    expect($user->vacations[0]->description)->toBe('Testing');
    expect($user->vacations[1]->description)->toBe('Testing 2');

    $vacation = $user->vacations[0];
    $vacation->start_date = '2023-01-07';
    $vacation->description = 'Testing Updated';
    $this->actingAs($user)
        ->putJson('/user/vacations', [
            'vacations' => [$vacation->toArray()],
        ])
        ->assertRedirect('/user/availability');

    $user->refresh()->load(['vacations']);
    expect($user->vacations)->toHaveCount(2);
    expect($user->vacations[0]->start_date)->toBe('2023-01-07');
    expect($user->vacations[0]->description)->toBe('Testing Updated');
    expect($user->vacations[1]->description)->toBe('Testing 2');

    $this->actingAs($user)
        ->putJson('/user/vacations', [
            'deletedVacations' => [['id' => $vacation->getKey()]],
        ])
        ->assertRedirect('/user/availability');

    $user->refresh()->load(['vacations']);
    expect($user->vacations)->toHaveCount(1);
    expect($user->vacations[0]->description)->toBe('Testing 2');
});

test('admin can maintain his own vacations', function () {
    $admin = User::factory()->enabled()->adminRoleUser()->create();

    $vacation1 = ['start_date' => '2023-01-01', 'end_date' => '2023-01-15', 'description' => 'Testing'];
    $vacation = UserVacation::factory()
        ->state($vacation1)
        ->for($admin)
        ->create();
    $data = UserVacationData::from($vacation);

    $vacationData = [
        'vacations' => [
            $data->toArray(),
            ['start_date' => '2023-02-01', 'end_date' => '2023-02-15', 'description' => 'Testing 2'],
        ],
    ];

    $this->actingAs($admin)
        ->putJson('/user/vacations', $vacationData)
        ->assertRedirect('/user/availability');

    $admin->refresh()->load(['vacations']);
    expect($admin->vacations)->toHaveCount(2);
    expect($admin->vacations[0]->description)->toBe('Testing');
    expect($admin->vacations[1]->description)->toBe('Testing 2');
});

test('user cannot add vacations for other users', function () {
    $settings = app()->make(GeneralSettings::class);
    $settings->enableUserAvailability = true;
    $settings->save();

    $user = User::factory()->enabled()->create();
    $user2 = User::factory()->enabled()->create();

    $vacationData = [
        'user_id' => $user2->id,
        'vacations' => [
            ['start_date' => '2023-01-01', 'end_date' => '2023-01-15', 'description' => 'Testing'],
            ['start_date' => '2023-02-01', 'end_date' => '2023-02-15', 'description' => 'Testing 2'],
        ],
    ];

    $this->actingAs($user)
        ->putJson('/user/vacations', $vacationData)
        ->assertInvalid([
            'user_id',
        ]);

    $this->assertDatabaseCount('user_vacations', 0);
});

test('user cannot update vacations using another users vacation id', function () {
    $settings = app()->make(GeneralSettings::class);
    $settings->enableUserAvailability = true;
    $settings->save();

    $user = User::factory()->enabled()->create();
    $user2 = User::factory()->enabled()->create();

    $state = [
        'user_id' => $user2->id,
        'start_date' => '2023-01-01',
        'end_date' => '2023-01-15',
        'description' => 'Testing',
    ];

    $vacation = UserVacation::factory()->state($state)->create();

    $vacationData = [
        'vacations' => [
            [
                'id' => $vacation->id, 'start_date' => '2024-01-01', 'end_date' => '2024-01-15',
                'description' => 'Updated Testing',
            ],
        ],
    ];

    $this->actingAs($user)
        ->putJson('/user/vacations', $vacationData)
        ->assertUnprocessable();

    $found = UserVacation::find($vacation->id);
    expect($found->start_date)->toBe($state['start_date']);
    expect($found->end_date)->toBe($state['end_date']);
    expect($found->description)->toBe($state['description']);
});

test('user cannot delete vacations using another users vacation id', function () {
    $settings = app()->make(GeneralSettings::class);
    $settings->enableUserAvailability = true;
    $settings->save();

    $user = User::factory()->enabled()->create();
    $user2 = User::factory()->enabled()->create();

    $state = [
        'user_id' => $user2->id,
        'start_date' => '2023-01-01',
        'end_date' => '2023-01-15',
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
        ->putJson('/user/vacations', $vacationData)
        ->assertUnprocessable();

    $this->assertDatabaseCount('user_vacations', 1);
});

test('user vacations data is validated', function () {
    $settings = app()->make(GeneralSettings::class);
    $settings->enableUserAvailability = true;
    $settings->save();

    $user = User::factory()->enabled()->create();

    $vacationData = [
        'user_id' => User::latest('id')->first()->getKey() + 1,
        'vacations' => [
            ['start_date' => '2023-01-01', 'end_date' => '2023-01-15', 'description' => 'Testing'],
            ['start_date' => 'test', 'end_date' => '', 'description' => 'Testing 2'],
            ['start_date' => '2023-03-01', 'end_date' => '2023-03-15', 'description' => Str::repeat('1', 300)],
        ],
        'deletedVacations' => [
            ['id' => 5555],
        ],
    ];

    $this->actingAs($user)
        ->putJson('/user/vacations', $vacationData)
        ->assertUnprocessable()
        ->assertInvalid([
            'user_id',
            'vacations.1.start_date',
            'vacations.1.end_date',
            'vacations.2.description',
            'deletedVacations.0.id',
        ]);
});

test('user vacations dates cannot be in wrong order', function () {
    $settings = app()->make(GeneralSettings::class);
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
        ->putJson('/user/vacations', $vacationData)
        ->assertUnprocessable()
        ->assertValid('vacations.0')
        ->assertInvalid([
            'vacations.1.start_date',
            'vacations.1.end_date',
        ]);
});

test('user can maintain location choices', function () {
    $settings = app()->make(GeneralSettings::class);
    $settings->enableUserLocationChoices = true;
    $settings->save();

    $user = User::factory()->enabled()->create();
    $locations = Location::factory()
        ->count(3)
        ->sequence(fn (Sequence $sequence) => ['name' => 'Location '.$sequence->index])
        ->create();

    $choiceData = [
        'selectedLocations' => [
            $locations[0]->getKey(),
            $locations[2]->getKey(),
        ],
    ];

    $this->actingAs($user)
        ->putJson('/user/available-locations', $choiceData)
        ->assertRedirect('/user/availability')
        ->assertSessionHas('flash.banner', 'your preferred locations have been updated.');

    $user->refresh()->load(['rosterLocations']);
    expect($user->rosterLocations)->toHaveCount(2);
    expect($user->rosterLocations[0]->name)->toBe($locations[0]->name);
    expect($user->rosterLocations[1]->name)->toBe($locations[2]->name);
    $this->assertNotSame($locations[1]->name, $user->rosterLocations[0]->name, 'Verify data is not duplicated');

    $choiceData['selectedLocations'][1] = $locations[1]->getKey();

    $this->actingAs($user)
        ->putJson('/user/available-locations', $choiceData)
        ->assertRedirect('/user/availability');

    $user->refresh()->load(['rosterLocations']);
    $rosterLocations = $user->rosterLocations->pluck('name');
    expect($rosterLocations)->toContain($locations[0]->name);
    expect($rosterLocations)->toContain($locations[1]->name);
});

test('user can get user location choices', function () {
    $settings = app()->make(GeneralSettings::class);
    $settings->enableUserLocationChoices = true;
    $settings->save();

    $user = User::factory()->enabled()->create();
    $locations = Location::factory()->count(3)->create();

    $this->actingAs($user)
        ->getJson('/user/available-locations')
        ->assertOk()
        ->assertJsonCount(3)
        ->assertJsonFragment(['id' => $locations[0]->id, 'name' => $locations[0]->name])
        ->assertJsonFragment(['id' => $locations[1]->id, 'name' => $locations[1]->name])
        ->assertJsonFragment(['id' => $locations[2]->id, 'name' => $locations[2]->name]);
});

test('user cant maintain disabled feature of user location choices', function () {
    $user = User::factory()->enabled()->create();
    $locations = Location::factory()->count(3)->create();

    $this->actingAs($user)
        ->getJson('/user/available-locations')
        ->assertInvalid(['featureDisabled']);

    $choiceData = [
        'selectedLocations' => [
            $locations[0]->getKey(),
            $locations[2]->getKey(),
        ],
    ];
    $this->actingAs($user)
        ->putJson('/user/available-locations', $choiceData)
        ->assertInvalid(['featureDisabled']);
});

test('non admin cannot retrieve another users location choices or vacations', function () {
    $settings = app()->make(GeneralSettings::class);
    $settings->enableUserLocationChoices = true;
    $settings->save();

    $user = User::factory()->enabled()->create();
    $user2 = User::factory()->enabled()->create();
    $locations = Location::factory()->count(3)->create();

    $choiceData = [
        'user_id' => $user2->getKey(),
        'selectedLocations' => [
            $locations[0]->getKey(),
            $locations[2]->getKey(),
        ],
    ];

    $this->actingAs($user)
        ->putJson('/user/available-locations', $choiceData)
        ->assertUnauthorized();
});

test('user cant maintain disabled feature of user regular availability', function () {
    $user = User::factory()->enabled()->create();

    GeneralSettings::fake(['enableUserAvailability' => false]);

    $this->actingAs($user)
        ->getJson('/user/availability')
        ->assertInvalid(['featureDisabled']);

    $availabilityData = [
        'day_monday' => [7, 18],
        'day_tuesday' => [12, 13, 14, 15, 16, 17, 18],
        'day_wednesday' => [7, 18],
        'day_thursday' => [7, 18],
        'day_friday' => [7, 18],
        'day_saturday' => [7, 18],
        'day_sunday' => [12, 13, 14, 15, 16, 17, 18],
        'num_mondays' => 0,
        'num_tuesdays' => 4,
        'num_wednesdays' => 0,
        'num_thursdays' => 0,
        'num_fridays' => 0,
        'num_saturdays' => 0,
        'num_sundays' => 1,
        'comments' => 'Atque voluptatem debitis culpa. test',
    ];

    $this->actingAs($user)
        ->putJson('/user/availability', $availabilityData)
        ->assertInvalid(['featureDisabled']);
});

test('user can see their regular availability if never used', function () {
    $settings = app()->make(GeneralSettings::class);
    $settings->enableUserAvailability = true;
    $settings->save();

    /** @var User $user */
    $user = User::factory()->enabled()->create();

    $this->actingAs($user)
        ->get('/user/availability')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Profile/ShowAvailability')
            ->has('availability', fn (AssertableInertia $data) => $data
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
});

test('user can see their saved regular availability', function () {
    $settings = app()->make(GeneralSettings::class);
    $settings->enableUserAvailability = true;
    $settings->save();

    /** @var User $user */
    $user = User::factory()->enabled()->create();

    $availability = new UserAvailability(['user_id' => $user->id]);
    $availability->num_wednesdays = 2;
    $availability->day_wednesday = [7, 18];
    $availability->comments = 'Testing';

    $user->availability = $availability;
    $user->availability->save();

    $user->load('availability');

    $this->actingAs($user)
        ->get('/user/availability')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Profile/ShowAvailability')
            ->has('availability', fn (AssertableInertia $data) => $data
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
});

test('user can update their regular availability', function () {
    $settings = app()->make(GeneralSettings::class);
    $settings->enableUserAvailability = true;
    $settings->save();

    /** @var User $user */
    $user = User::factory()->enabled()->create();

    $availability = new UserAvailability(['user_id' => $user->id]);
    $availability->user_id = $user->id;
    $availability->num_wednesdays = 2;
    $availability->day_wednesday = [7, 18];
    $availability->comments = 'Testing';
    $user->availability = $availability;
    $user->availability->save();

    $this->actingAs($user)
        ->putJson('/user/availability', [
            'day_monday' => null,
            'day_tuesday' => null,
            'day_wednesday' => [10, 13],
            'day_thursday' => [10, 13],
            'day_friday' => null,
            'day_saturday' => null,
            'day_sunday' => null,
            'num_mondays' => 0,
            'num_tuesdays' => 0,
            'num_wednesdays' => 1,
            'num_thursdays' => 1,
            'num_fridays' => 0,
            'num_saturdays' => 0,
            'num_sundays' => 0,
            'comments' => 'Testing 123',
        ])
        ->assertRedirect('/user/availability');

    $availability->refresh();
    expect($availability->num_wednesdays)->toBe(1);
    expect($availability->num_thursdays)->toBe(1);
    expect($availability->day_wednesday)->toHaveCount(4);
    expect($availability->day_thursday)->toHaveCount(4);
    expect($availability->day_wednesday[0])->toEqual(AvailabilityHours::Ten);
    expect($availability->day_wednesday[3])->toEqual(AvailabilityHours::Thirteen);
    expect($availability->day_thursday[0])->toEqual(AvailabilityHours::Ten);
    expect($availability->day_thursday[3])->toEqual(AvailabilityHours::Thirteen);
    expect($availability->comments)->toEqual('Testing 123');
});

test('user can create their regular availability', function () {
    $settings = app()->make(GeneralSettings::class);
    $settings->enableUserAvailability = true;
    $settings->save();

    /** @var User $user */
    $user = User::factory()->enabled()->create();

    $this->actingAs($user)
        ->putJson('/user/availability', [
            'day_wednesday' => [10, 13],
            'day_thursday' => [10, 13],
            'num_mondays' => 0,
            'num_tuesdays' => 0,
            'num_wednesdays' => 1,
            'num_thursdays' => 1,
            'num_fridays' => 0,
            'num_saturdays' => 0,
            'num_sundays' => 0,
            'comments' => 'Testing 123',
        ])
        ->assertRedirect('/user/availability');

    $availability = UserAvailability::firstwhere('user_id', $user->id);
    expect($availability->num_wednesdays)->toBe(1);
    expect($availability->num_thursdays)->toBe(1);
    expect($availability->day_wednesday)->toHaveCount(4);
    expect($availability->day_thursday)->toHaveCount(4);
    expect($availability->day_wednesday[0])->toEqual(AvailabilityHours::Ten);
    expect($availability->day_wednesday[3])->toEqual(AvailabilityHours::Thirteen);
    expect($availability->day_thursday[0])->toEqual(AvailabilityHours::Ten);
    expect($availability->day_thursday[3])->toEqual(AvailabilityHours::Thirteen);
    expect($availability->comments)->toEqual('Testing 123');
});

test('user regular availability data is validated', function () {
    $settings = app()->make(GeneralSettings::class);
    $settings->enableUserAvailability = true;
    $settings->save();

    /** @var User $user */
    $user = User::factory()->enabled()->create();
    $this->actingAs($user)
        ->putJson('/user/availability', [
            'day_wednesday' => [13], // minimum of 2 values
            'day_thursday' => [], // required
            'day_friday' => [18, 24],
            'day_saturday' => [12, 16], // valid but should be ignored because of num_saturdays
            'num_mondays' => false, // invalid value
            // tuesday is missing
            'num_wednesdays' => 1, // valid value but no corresponding day value
            'num_thursdays' => 5, // no more than 4 thursdays
            'num_fridays' => 1,
            'num_saturdays' => 0,
            'num_sundays' => 'test', // invalid value
            'comments' => Str::repeat('1', 600), // too long
        ])
        ->assertUnprocessable()
        ->assertInvalid([
            'day_wednesday' => 'This field must have at least 2 items',
            'day_friday.6' => 'The day_friday.6 field must not be greater than 23.',
            'num_mondays' => 'The num mondays field must be an integer.',
            'num_tuesdays' => 'The num tuesdays field is required.',
            'num_thursdays' => 'The num thursdays field must not be greater than 4.',
            'num_sundays' => 'The num sundays field must be an integer.',
            'comments' => 'The comments field must not be greater than 500 characters.',
        ]);
});

test('user cannot maintain availability for other users', function () {
    $settings = app()->make(GeneralSettings::class);
    $settings->enableUserAvailability = true;
    $settings->save();

    $user = User::factory()->enabled()->create();
    $user2 = User::factory()->enabled()->create();

    $this->actingAs($user)
        ->putJson('/user/availability', [
            'user_id' => $user2->id,
            'day_wednesday' => [10, 13],
            'day_thursday' => [10, 13],
            'num_mondays' => 0,
            'num_tuesdays' => 0,
            'num_wednesdays' => 1,
            'num_thursdays' => 1,
            'num_fridays' => 0,
            'num_saturdays' => 0,
            'num_sundays' => 0,
            'comments' => 'Testing 123',
        ])
        ->assertUnauthorized();

    $this->assertDatabaseCount('user_availabilities', 0);
});

test('user is prompted to update availability', function () {
    $settings = app()->make(GeneralSettings::class);
    $settings->enableUserAvailability = true;
    $settings->save();

    $user = User::factory()->enabled()->create();

    // First confirm that the user is prompted to update their availability
    $this->actingAs($user)
        ->get('/')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->where('needsToUpdateAvailability', true)
        )
        ->assertSuccessful();

    $user->load('availability');

    // Then user should view their availability
    $this->actingAs($user)
        ->get('/user/availability')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Profile/ShowAvailability')
            ->where('needsToUpdateAvailability', true)
        )
        ->assertSuccessful();

    // Travel to a future date before 'flagging' the user for having updated their availability. This will ensure
    // that the user isn't prompted to update their availability after 'viewing' it.
    $this->travelTo(Carbon::now()->addDay());

    // This should happen automatically when navigating to /user/availability but will only work if the user has navigated to /user/availability
    $this->actingAs($user)
        ->postJson('/set-viewed-availability')
        ->assertSuccessful()
        ->assertContent('');

    $this->actingAs($user)
        ->get('/')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->whereNot('needsToUpdateAvailability', true)
        )
        ->assertSuccessful();
});
