<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ShowExportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_exports_page(): void
    {
        $admin = User::factory()->enabled()->adminRoleUser()->create();

        $this->actingAs($admin)
            ->get('/admin/exports')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Exports/Show')
            );
    }

    public function test_non_admin_cannot_view_exports_page(): void
    {
        $user = User::factory()->enabled()->create();

        $this->actingAs($user)
            ->get('/admin/exports')
            ->assertForbidden();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->get('/admin/exports')
            ->assertRedirect('/login');
    }
}
