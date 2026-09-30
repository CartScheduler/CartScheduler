<?php

use App\Models\User;
use App\Models\UserAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Traits\AssertsExportResponses;

uses(AssertsExportResponses::class);

uses(RefreshDatabase::class);

test('admin can download user availabilities as csv', function () {
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

    expect($rows[0][0])->toBe('id');
    expect($rows[0][array_key_last($rows[0])])->toBe('comments');
    expect($rows[1][1])->toBe('Alice Volunteer');
    expect($rows[1][array_key_last($rows[1])])->toBe('Available Wed/Thu mornings');
});

test('non admin cannot download user availabilities', function () {
    $user = User::factory()->enabled()->create();

    $this->actingAs($user)
        ->get('/admin/exports/user-availabilities')
        ->assertForbidden();
});

test('unauthenticated request is rejected', function () {
    $this->get('/admin/exports/user-availabilities')
        ->assertRedirect('/login');
});
