<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AssertsExportResponses;
use Tests\Traits\ExtraFunctions;

class ExportUserShiftCountsTest extends TestCase
{
    use AssertsExportResponses;
    use ExtraFunctions;
    use RefreshDatabase;

    public function test_admin_can_download_shift_counts_as_csv(): void
    {
        $admin = User::factory()->enabled()->adminRoleUser()->create();
        $alice = User::factory()->enabled()->create([
            'name' => 'Alice Volunteer',
            'email' => 'alice@example.com',
        ]);
        $location = Location::factory()->create();
        $shift = Shift::factory()->everyDay9am()->for($location)->create();

        $this->attachUserToShift($shift->id, $alice, '2024-01-10');
        $this->attachUserToShift($shift->id, $alice, '2024-01-15');

        $rows = $this->assertExportCsvDownload(
            $this->actingAs($admin)
                ->get('/admin/exports/shift-counts?start_date=2024-01-01&end_date=2024-01-31'),
            'shift-counts',
        );

        $this->assertSame(['id', 'name', 'email', 'shift_count'], $rows[0]);
        $this->assertSame('Alice Volunteer', $rows[1][1]);
        $this->assertSame('2', $rows[1][3]);
    }

    public function test_shift_counts_are_limited_to_date_range(): void
    {
        $admin = User::factory()->enabled()->adminRoleUser()->create();
        $alice = User::factory()->enabled()->create(['name' => 'Alice Volunteer']);
        $location = Location::factory()->create();
        $shift = Shift::factory()->everyDay9am()->for($location)->create();

        $this->attachUserToShift($shift->id, $alice, '2024-01-10');
        $this->attachUserToShift($shift->id, $alice, '2024-02-15');

        $rows = $this->assertExportCsvDownload(
            $this->actingAs($admin)
                ->get('/admin/exports/shift-counts?start_date=2024-01-01&end_date=2024-01-31'),
            'shift-counts',
        );

        $this->assertCount(2, $rows);
        $this->assertSame('1', $rows[1][3]);
    }

    public function test_non_admin_cannot_download_shift_counts(): void
    {
        $user = User::factory()->enabled()->create();

        $this->actingAs($user)
            ->get('/admin/exports/shift-counts?start_date=2024-01-01&end_date=2024-01-31')
            ->assertForbidden();
    }

    public function test_shift_counts_require_date_range(): void
    {
        $admin = User::factory()->enabled()->adminRoleUser()->create();

        $this->actingAs($admin)
            ->get('/admin/exports/shift-counts')
            ->assertSessionHasErrors(['start_date', 'end_date']);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->get('/admin/exports/shift-counts?start_date=2024-01-01&end_date=2024-01-31')
            ->assertRedirect('/login');
    }
}
