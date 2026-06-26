<?php

namespace Tests\Feature\App\Admin;

use App\Models\Location;
use App\Models\Report;
use App\Models\Shift;
use App\Models\ShiftUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_retrieve_reports(): void
    {
        $this->travelTo('2023-02-01');

        $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);
        $user  = User::factory()->userRoleUser()->create(['is_enabled' => true]);

        Location::factory()
            ->state(['max_volunteers' => 3])
            ->has(Shift::factory()
                ->everyDay9am()
                ->hasAttached(
                    User::factory()
                        ->userRoleUser()
                        ->count(3)
                        ->state(['is_enabled' => true])
                    , ['shift_date' => '2023-01-03']
                )
            )
            ->create();

        $shiftIds = ShiftUser::all()->map(fn(ShiftUser $shiftUser) => ['shift_id' => $shiftUser->shift_id]);

        Report::factory()
            ->count($shiftIds->count())
            ->sequence(...$shiftIds->toArray())
            ->create();

        $this->actingAs($admin)
            ->get("/admin/reports")
            ->assertSuccessful()
            ->assertInertia(fn(AssertableInertia $page) => $page
                ->component('Admin/Reports/List')
                ->has('reports', 3)
            );

        // Confirm that non-admin cannot access reports
        $this->actingAs($user)
            ->get("/admin/reports")
            ->assertForbidden();
    }

    public function test_reports_work_without_metadata(): void
    {
        $this->travelTo('2023-02-01');

        $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);

        Location::factory()
            ->state(['max_volunteers' => 3])
            ->has(Shift::factory()
                ->everyDay9am()
                ->hasAttached(
                    User::factory()
                        ->userRoleUser()
                        ->count(3)
                        ->state(['is_enabled' => true])
                    , ['shift_date' => '2023-01-03']
                )
            )
            ->create();

        $shiftIds = ShiftUser::all()->map(fn(ShiftUser $shiftUser) => ['shift_id' => $shiftUser->shift_id]);

        Report::factory(state: ['metadata' => null])
            ->count($shiftIds->count())
            ->sequence(...$shiftIds->toArray())
            ->create();

        $this->actingAs($admin)
            ->get("/admin/reports")
            ->assertSuccessful()
            ->assertInertia(fn(AssertableInertia $page) => $page
                ->component('Admin/Reports/List')
                ->has('reports', fn(AssertableInertia $data) => $data
                    ->whereNull('0.metadata')
                    ->whereNull('1.metadata')
                    ->whereNull('2.metadata')
                    ->etc()
                )
            );
    }

    public function test_reports_are_limited_to_last_two_months(): void
    {
        $this->travelTo('2024-03-15');

        $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);
        $location = Location::factory()->create();
        $shift = Shift::factory()->everyDay9am()->for($location)->create();

        Report::factory()->create([
            'shift_id' => $shift->id,
            'shift_date' => '2024-02-01',
        ]);
        Report::factory()->create([
            'shift_id' => $shift->id,
            'shift_date' => '2024-01-10',
        ]);

        $this->actingAs($admin)
            ->get('/admin/reports')
            ->assertSuccessful()
            ->assertInertia(fn(AssertableInertia $page) => $page
                ->component('Admin/Reports/List')
                ->has('reports', 1)
                ->where('reports.0.shift_date', '2024-02-01')
            );
    }
}
