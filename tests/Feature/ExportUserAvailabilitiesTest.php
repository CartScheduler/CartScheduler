<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AssertsExportResponses;

class ExportUserAvailabilitiesTest extends TestCase
{
    use AssertsExportResponses;
    use RefreshDatabase;

    public function test_admin_can_download_user_availabilities_as_csv(): void
    {
        $admin = User::factory()->enabled()->adminRoleUser()->create();
        $user = User::factory()->enabled()->create([
            'name' => 'Alice Volunteer',
        ]);
        UserAvailability::factory()
            ->wedThuTenToOne()
            ->create([
                'user_id' => $user->id,
                'comments' => 'Available Wed/Thu mornings',
            ]);

        $rows = $this->assertExportCsvDownload(
            $this->actingAs($admin)
                ->get('/admin/exports/user-availabilities'),
            'user-availabilities',
        );

        $this->assertSame('id', $rows[0][0]);
        $this->assertSame('comments', $rows[0][array_key_last($rows[0])]);
        $this->assertSame('Alice Volunteer', $rows[1][1]);
        $this->assertSame('Available Wed/Thu mornings', $rows[1][array_key_last($rows[1])]);
    }

    public function test_non_admin_cannot_download_user_availabilities(): void
    {
        $user = User::factory()->enabled()->create();

        $this->actingAs($user)
            ->get('/admin/exports/user-availabilities')
            ->assertForbidden();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->get('/admin/exports/user-availabilities')
            ->assertRedirect('/login');
    }
}
