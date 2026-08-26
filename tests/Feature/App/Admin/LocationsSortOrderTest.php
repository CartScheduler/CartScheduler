<?php

use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

test('system can handle no sort order', function () {
    $admin = User::factory()->enabled()->adminRoleUser()->create();

    Location::factory()
        ->count(6)
        ->has(Shift::factory()->everyDay9am())
        ->create();

    $this->actingAs($admin)->getJson('/admin/locations')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Locations/List')
            ->has('locations', fn (AssertableInertia $data) => $data
                ->where('0.sort_order', 0)
                ->where('1.sort_order', 0)
                ->where('2.sort_order', 0)
                ->where('3.sort_order', 0)
                ->where('4.sort_order', 0)
                ->where('5.sort_order', 0)
            ));
});

test('system can handle partial sort order update', function () {
    $admin = User::factory()->enabled()->adminRoleUser()->create();

    $locations = Location::factory()
        ->count(6)
        ->sequence(fn (Sequence $sequence) => ['sort_order' => $sequence->index])
        ->has(Shift::factory()->everyDay9am())
        ->create();

    $this->assertDatabaseHas('locations', [
        'id' => $locations[3]->id,
        'sort_order' => 3,
    ]);

    $this->assertDatabaseHas('locations', [
        'id' => $locations[4]->id,
        'sort_order' => 4,
    ]);

    // Update the sort order of two items
    $this->actingAs($admin)
        ->putJson('/admin/locations/sort-order', [
            'locations' => [
                $locations[3]->id,
                $locations[4]->id,
            ],
        ])
        ->assertOk();

    $this->assertDatabaseHas('locations', [
        'id' => $locations[0]->id,
        'sort_order' => 0,
    ]);

    $this->assertDatabaseHas('locations', [
        'id' => $locations[1]->id,
        'sort_order' => 1,
    ]);

    $this->assertDatabaseHas('locations', [
        'id' => $locations[3]->id,
        'sort_order' => 0,
    ]);

    $this->assertDatabaseHas('locations', [
        'id' => $locations[4]->id,
        'sort_order' => 1,
    ]);
});

test('admin can update sort order', function () {
    $admin = User::factory()->enabled()->adminRoleUser()->create();

    $locations = Location::factory()
        ->count(6)
        ->has(Shift::factory()->everyDay9am())
        ->create();

    $modelIds = $locations->pluck('id')->toArray();

    $this->actingAs($admin)
        ->putJson('/admin/locations/sort-order', [
            'locations' => [
                $modelIds[5],
                $modelIds[4],
                $modelIds[3],
                $modelIds[2],
                $modelIds[1],
                $modelIds[0],
            ],
        ])
        ->assertOk();

    $locations->each(fn (Location $location, int $index) => $location->refresh());

    expect($locations[0]->sort_order)->toBe(5);
    expect($locations[0]->id)->toBe($modelIds[0]);
    expect($locations[1]->sort_order)->toBe(4);
    expect($locations[1]->id)->toBe($modelIds[1]);
    expect($locations[2]->sort_order)->toBe(3);
    expect($locations[2]->id)->toBe($modelIds[2]);
    expect($locations[3]->sort_order)->toBe(2);
    expect($locations[3]->id)->toBe($modelIds[3]);
    expect($locations[4]->sort_order)->toBe(1);
    expect($locations[4]->id)->toBe($modelIds[4]);
    expect($locations[5]->sort_order)->toBe(0);
    expect($locations[5]->id)->toBe($modelIds[5]);

    $this->actingAs($admin)->getJson('/admin/locations')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Locations/List')
            ->has('locations', fn (AssertableInertia $data) => $data
                ->where('0.sort_order', 0)
                ->where('1.sort_order', 1)
                ->where('2.sort_order', 2)
                ->where('3.sort_order', 3)
                ->where('4.sort_order', 4)
                ->where('5.sort_order', 5)
            ));
});

test('fallback to name sort when sort order duplicated', function () {
    $admin = User::factory()->enabled()->adminRoleUser()->create();

    Location::factory()
        ->count(6)
        ->sequence(fn (Sequence $sequence) => ['name' => 'aaa'. 5 - $sequence->index])
        ->state(['sort_order' => 1])
        ->has(Shift::factory()->everyDay9am())
        ->create();

    $this->actingAs($admin)->getJson('/admin/locations')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Locations/List')
            ->has('locations', fn (AssertableInertia $data) => $data
                ->where('0.name', 'aaa0')
                ->where('1.name', 'aaa1')
                ->where('2.name', 'aaa2')
                ->where('3.name', 'aaa3')
                ->where('4.name', 'aaa4')
                ->where('5.name', 'aaa5')
            ));
});
