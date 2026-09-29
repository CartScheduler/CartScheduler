<?php

use App\Data\FilledShiftData;
use App\Enums\CacheKey;
use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Cache serialization
|--------------------------------------------------------------------------
|
| Laravel 13 gates which classes may be unserialized from the cache through
| `cache.serializable_classes`. The dashboard is the only place that caches
| PHP objects, so these tests run against a store that really serializes -
| the array store the rest of the suite uses would hide a missing entry.
|
*/

beforeEach(function (): void {
    config()->set('cache.default', 'file');
    Cache::store('file')->flush();
});

afterEach(function (): void {
    Cache::store('file')->flush();
});

test('dashboard data survives a real cache round trip', function () {
    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);
    $users = User::factory()->enabled()->count(4)->create()->chunk(2);

    Location::factory()
        ->allPublishers()
        ->has(
            Shift::factory()
                ->everyDay9am()
                ->hasAttached($users->first(), ['shift_date' => '2023-01-03'])
                ->hasAttached($users->last(), ['shift_date' => '2023-01-04'])
        )
        ->create();

    $this->travelTo('2023-01-03 09:00:00');

    // The first request populates the cache, the second reads it back through
    // `unserialize`, which is where a missing allow-list entry would surface.
    foreach (range(1, 2) as $ignored) {
        $this->actingAs($admin)
            ->get('/admin/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Dashboard')
                ->where('totalUsers', User::count())
                ->where('totalLocations', Location::count())
                ->has('shiftFilledData', fn (AssertableInertia $data) => $data
                    ->where('0.date', '2023-01-03')
                    ->has('0.shifts_filled')
                    ->has('0.shifts_available')
                    ->etc()
                )
            );
    }
});

test('the configured allow list covers the data objects the dashboard caches', function () {
    $filled = [new FilledShiftData('2023-01-03', 2, 10)];

    Cache::store('file')->put(CacheKey::ShiftFilledData->value, $filled, 60);

    expect(Cache::store('file')->get(CacheKey::ShiftFilledData->value))
        ->toBeArray()
        ->and(Cache::store('file')->get(CacheKey::ShiftFilledData->value)[0])
        ->toBeInstanceOf(FilledShiftData::class)
        ->and(Cache::store('file')->get(CacheKey::ShiftFilledData->value)[0]->date)
        ->toBe('2023-01-03');
});

test('classes outside the allow list are not unserialized from the cache', function () {
    Cache::store('file')->put('unlisted-object', new stdClass, 60);

    expect(Cache::store('file')->get('unlisted-object'))
        ->not->toBeInstanceOf(stdClass::class);
});
