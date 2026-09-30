<?php

use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Traits\AssertsExportResponses;
use Tests\Traits\ExtraFunctions;

uses(AssertsExportResponses::class);

uses(ExtraFunctions::class);

uses(RefreshDatabase::class);

test('admin can download shift counts as csv', function () {
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

    expect($rows[0])->toBe(['id', 'name', 'email', 'shift_count']);
    expect($rows[1][1])->toBe('Alice Volunteer');
    expect($rows[1][3])->toBe('2');
});

test('shift counts are limited to date range', function () {
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

    expect($rows)->toHaveCount(2);
    expect($rows[1][3])->toBe('1');
});

test('non admin cannot download shift counts', function () {
    $user = User::factory()->enabled()->create();

    $this->actingAs($user)
        ->get('/admin/exports/shift-counts?start_date=2024-01-01&end_date=2024-01-31')
        ->assertForbidden();
});

test('shift counts require date range', function () {
    $admin = User::factory()->enabled()->adminRoleUser()->create();

    $this->actingAs($admin)
        ->get('/admin/exports/shift-counts')
        ->assertSessionHasErrors(['start_date', 'end_date']);
});

test('unauthenticated request is rejected', function () {
    $this->get('/admin/exports/shift-counts?start_date=2024-01-01&end_date=2024-01-31')
        ->assertRedirect('/login');
});
