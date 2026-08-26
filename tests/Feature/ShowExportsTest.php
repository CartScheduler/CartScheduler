<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

test('admin can view exports page', function () {
    $admin = User::factory()->enabled()->adminRoleUser()->create();

    $this->actingAs($admin)
        ->get('/admin/exports')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Exports/Show')
        );
});

test('non admin cannot view exports page', function () {
    $user = User::factory()->enabled()->create();

    $this->actingAs($user)
        ->get('/admin/exports')
        ->assertForbidden();
});

test('unauthenticated request is rejected', function () {
    $this->get('/admin/exports')
        ->assertRedirect('/login');
});
