<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Report;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AssertsExportResponses;
use Tests\Traits\MakesTags;

class ExportReportsTest extends TestCase
{
    use AssertsExportResponses;
    use MakesTags;
    use RefreshDatabase;

    public function test_admin_can_download_reports_as_csv(): void
    {
        $admin = User::factory()->enabled()->adminRoleUser()->create();
        $user = User::factory()->enabled()->create([
            'name' => 'Alice Volunteer',
            'email' => 'alice@example.com',
            'mobile_phone' => '0412345678',
        ]);
        $location = Location::factory()->create(['name' => 'Main Cart']);
        $shift = Shift::factory()->everyDay9am()->for($location)->create();

        $report = Report::factory()->create([
            'shift_id' => $shift->id,
            'report_submitted_user_id' => $user->id,
            'shift_date' => '2024-01-10',
            'placements_count' => 3,
            'videos_count' => 2,
            'requests_count' => 1,
            'comments' => 'Good shift',
            'shift_was_cancelled' => false,
            'metadata' => [
                'shift_id' => $shift->id,
                'shift_time' => '09:00:00',
                'location_id' => $location->id,
                'location_name' => 'Main Cart',
                'submitted_by_id' => $user->id,
                'submitted_by_name' => 'Alice Volunteer',
                'submitted_by_email' => 'alice@example.com',
                'submitted_by_phone' => '0412345678',
                'associates' => [
                    ['id' => 99, 'name' => 'Bob Associate'],
                ],
            ],
        ]);

        $tags = $this->makeTags(2);
        $report->syncTags($tags);

        $rows = $this->assertExportCsvDownload(
            $this->actingAs($admin)
                ->get('/admin/exports/reports?start_date=2024-01-01&end_date=2024-01-31'),
            'reports',
        );

        $this->assertSame([
            'id',
            'shift_date',
            'placements_count',
            'videos_count',
            'requests_count',
            'comments',
            'shift_was_cancelled',
            'tags',
            'metadata_shift_id',
            'metadata_shift_time',
            'metadata_location_id',
            'metadata_location_name',
            'metadata_submitted_by_id',
            'metadata_submitted_by_name',
            'metadata_submitted_by_email',
            'metadata_submitted_by_phone',
            'metadata_associates',
        ], $rows[0]);
        $this->assertSame((string) $report->id, $rows[1][0]);
        $this->assertSame('2024-01-10', $rows[1][1]);
        $this->assertSame('3', $rows[1][2]);
        $this->assertSame('Good shift', $rows[1][5]);
        $this->assertSame('0', $rows[1][6]);
        $this->assertSame('Main Cart', $rows[1][11]);
        $this->assertSame('Bob Associate', $rows[1][16]);
    }

    public function test_reports_are_limited_to_date_range(): void
    {
        $admin = User::factory()->enabled()->adminRoleUser()->create();
        $user = User::factory()->enabled()->create();
        $location = Location::factory()->create();
        $shift = Shift::factory()->everyDay9am()->for($location)->create();

        Report::factory()->create([
            'shift_id' => $shift->id,
            'report_submitted_user_id' => $user->id,
            'shift_date' => '2024-01-10',
            'metadata' => null,
        ]);
        Report::factory()->create([
            'shift_id' => $shift->id,
            'report_submitted_user_id' => $user->id,
            'shift_date' => '2024-02-15',
            'metadata' => null,
        ]);

        $rows = $this->assertExportCsvDownload(
            $this->actingAs($admin)
                ->get('/admin/exports/reports?start_date=2024-01-01&end_date=2024-01-31'),
            'reports',
        );

        $this->assertCount(2, $rows);
        $this->assertSame('2024-01-10', $rows[1][1]);
    }

    public function test_non_admin_cannot_download_reports(): void
    {
        $user = User::factory()->enabled()->create();

        $this->actingAs($user)
            ->get('/admin/exports/reports?start_date=2024-01-01&end_date=2024-01-31')
            ->assertForbidden();
    }

    public function test_reports_export_requires_date_range(): void
    {
        $admin = User::factory()->enabled()->adminRoleUser()->create();

        $this->actingAs($admin)
            ->get('/admin/exports/reports')
            ->assertSessionHasErrors(['start_date', 'end_date']);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->get('/admin/exports/reports?start_date=2024-01-01&end_date=2024-01-31')
            ->assertRedirect('/login');
    }
}
