<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AssertsExportResponses;
use Tests\Traits\ExtraFunctions;

class ExportShiftAssignmentsTest extends TestCase
{
    use AssertsExportResponses;
    use ExtraFunctions;
    use RefreshDatabase;

    public function test_admin_can_download_shift_assignments_as_csv(): void
    {
        $admin = User::factory()->enabled()->adminRoleUser()->create();
        $user = User::factory()->enabled()->create([
            'name' => 'Alice Volunteer',
            'email' => 'alice@example.com',
            'mobile_phone' => '555-0100',
        ]);
        $location = Location::factory()->create(['name' => 'Main Hall']);
        $shift = Shift::factory()->everyDay9am()->for($location)->create([
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
        ]);

        $this->attachUserToShift($shift->id, $user, '2024-01-10');

        $rows = $this->assertExportCsvDownload(
            $this->actingAs($admin)
                ->get('/admin/exports/shift-assignments?start_date=2024-01-01&end_date=2024-01-31'),
            'shift-assignments',
        );

        $this->assertSame(
            ['name', 'email', 'mobile_phone', 'shift_date', 'start_time', 'end_time', 'location'],
            $rows[0],
        );
        $this->assertSame('Alice Volunteer', $rows[1][0]);
        $this->assertSame('2024-01-10', $rows[1][3]);
        $this->assertSame('Main Hall', $rows[1][6]);
    }

    public function test_shift_assignments_are_limited_to_date_range(): void
    {
        $admin = User::factory()->enabled()->adminRoleUser()->create();
        $user = User::factory()->enabled()->create(['name' => 'Alice Volunteer']);
        $location = Location::factory()->create();
        $shift = Shift::factory()->everyDay9am()->for($location)->create();

        $this->attachUserToShift($shift->id, $user, '2024-01-10');
        $this->attachUserToShift($shift->id, $user, '2024-02-15');

        $rows = $this->assertExportCsvDownload(
            $this->actingAs($admin)
                ->get('/admin/exports/shift-assignments?start_date=2024-01-01&end_date=2024-01-31'),
            'shift-assignments',
        );

        $this->assertCount(2, $rows);
        $this->assertSame('2024-01-10', $rows[1][3]);
    }

    public function test_non_admin_cannot_download_shift_assignments(): void
    {
        $user = User::factory()->enabled()->create();

        $this->actingAs($user)
            ->get('/admin/exports/shift-assignments?start_date=2024-01-01&end_date=2024-01-31')
            ->assertForbidden();
    }

    public function test_shift_assignments_require_date_range(): void
    {
        $admin = User::factory()->enabled()->adminRoleUser()->create();

        $this->actingAs($admin)
            ->get('/admin/exports/shift-assignments')
            ->assertSessionHasErrors(['start_date', 'end_date']);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->get('/admin/exports/shift-assignments?start_date=2024-01-01&end_date=2024-01-31')
            ->assertRedirect('/login');
    }
}
