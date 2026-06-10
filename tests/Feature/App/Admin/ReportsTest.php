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
}
