<?php

namespace Tests\Feature\App\Admin;

use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LocationsSortOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_can_handle_no_sort_order(): void
    {
        $admin = User::factory()->enabled()->adminRoleUser()->create();

        Location::factory()
            ->count(6)
            ->has(Shift::factory()->everyDay9am())
            ->create();

        $this->actingAs($admin)->getJson("/admin/locations")
            ->assertOk()
            ->assertInertia(fn(AssertableInertia $page) => $page
                ->component('Admin/Locations/List')
                ->has('locations', fn(AssertableInertia $data) => $data
                    ->where('0.sort_order', 0)
                    ->where('1.sort_order', 0)
                    ->where('2.sort_order', 0)
                    ->where('3.sort_order', 0)
                    ->where('4.sort_order', 0)
                    ->where('5.sort_order', 0)
                ));
    }

    public function test_system_can_handle_partial_sort_order_update(): void
    {
        $admin = User::factory()->enabled()->adminRoleUser()->create();

        $locations = Location::factory()
            ->count(6)
            ->sequence(fn(Sequence $sequence) => ['sort_order' => $sequence->index])
            ->has(Shift::factory()->everyDay9am())
            ->create();

        $this->assertDatabaseHas('locations', [
            'id'         => $locations[3]->id,
            'sort_order' => 3,
        ]);

        $this->assertDatabaseHas('locations', [
            'id'         => $locations[4]->id,
            'sort_order' => 4,
        ]);

        // Update the sort order of two items
        $this->actingAs($admin)
            ->putJson("/admin/locations/sort-order", [
                'locations' => [
                    $locations[3]->id,
                    $locations[4]->id,
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('locations', [
            'id'         => $locations[0]->id,
            'sort_order' => 0,
        ]);

        $this->assertDatabaseHas('locations', [
            'id'         => $locations[1]->id,
            'sort_order' => 1,
        ]);

        $this->assertDatabaseHas('locations', [
            'id'         => $locations[3]->id,
            'sort_order' => 0,
        ]);

        $this->assertDatabaseHas('locations', [
            'id'         => $locations[4]->id,
            'sort_order' => 1,
        ]);
    }

    public function test_admin_can_update_sort_order(): void
    {
        $admin = User::factory()->enabled()->adminRoleUser()->create();

        $locations = Location::factory()
            ->count(6)
            ->has(Shift::factory()->everyDay9am())
            ->create();

        $modelIds = $locations->pluck('id')->toArray();

        $this->actingAs($admin)
            ->putJson("/admin/locations/sort-order", [
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

        $locations->each(fn(Location $location, int $index) => $location->refresh());

        $this->assertSame(5, $locations[0]->sort_order);
        $this->assertSame($modelIds[0], $locations[0]->id);
        $this->assertSame(4, $locations[1]->sort_order);
        $this->assertSame($modelIds[1], $locations[1]->id);
        $this->assertSame(3, $locations[2]->sort_order);
        $this->assertSame($modelIds[2], $locations[2]->id);
        $this->assertSame(2, $locations[3]->sort_order);
        $this->assertSame($modelIds[3], $locations[3]->id);
        $this->assertSame(1, $locations[4]->sort_order);
        $this->assertSame($modelIds[4], $locations[4]->id);
        $this->assertSame(0, $locations[5]->sort_order);
        $this->assertSame($modelIds[5], $locations[5]->id);

        $this->actingAs($admin)->getJson("/admin/locations")
            ->assertInertia(fn(AssertableInertia $page) => $page
                ->component('Admin/Locations/List')
                ->has('locations', fn(AssertableInertia $data) => $data
                    ->where('0.sort_order', 0)
                    ->where('1.sort_order', 1)
                    ->where('2.sort_order', 2)
                    ->where('3.sort_order', 3)
                    ->where('4.sort_order', 4)
                    ->where('5.sort_order', 5)
                ));
    }

    public function test_fallback_to_name_sort_when_sort_order_duplicated(): void
    {
        $admin = User::factory()->enabled()->adminRoleUser()->create();

        Location::factory()
            ->count(6)
            ->sequence(fn(Sequence $sequence) => ['name' => "aaa" . 5 -$sequence->index])
            ->state(['sort_order' => 1])
            ->has(Shift::factory()->everyDay9am())
            ->create();

        $this->actingAs($admin)->getJson("/admin/locations")
            ->assertInertia(fn(AssertableInertia $page) => $page
                ->component('Admin/Locations/List')
                ->has('locations', fn(AssertableInertia $data) => $data
                    ->where('0.name', 'aaa0')
                    ->where('1.name', 'aaa1')
                    ->where('2.name', 'aaa2')
                    ->where('3.name', 'aaa3')
                    ->where('4.name', 'aaa4')
                    ->where('5.name', 'aaa5')
                ));
    }
}
