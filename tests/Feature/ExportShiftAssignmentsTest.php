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

test('admin can download shift assignments as csv', function () {
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

    expect($rows[0])->toBe(['name', 'email', 'mobile_phone', 'shift_date', 'start_time', 'end_time', 'location']);
    expect($rows[1][0])->toBe('Alice Volunteer');
    expect($rows[1][3])->toBe('2024-01-10');
    expect($rows[1][6])->toBe('Main Hall');
});

test('shift assignments are limited to date range', function () {
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

    expect($rows)->toHaveCount(2);
    expect($rows[1][3])->toBe('2024-01-10');
});

test('non admin cannot download shift assignments', function () {
    $user = User::factory()->enabled()->create();

    $this->actingAs($user)
        ->get('/admin/exports/shift-assignments?start_date=2024-01-01&end_date=2024-01-31')
        ->assertForbidden();
});

test('shift assignments require date range', function () {
    $admin = User::factory()->enabled()->adminRoleUser()->create();

    $this->actingAs($admin)
        ->get('/admin/exports/shift-assignments')
        ->assertSessionHasErrors(['start_date', 'end_date']);
});

test('unauthenticated request is rejected', function () {
    $this->get('/admin/exports/shift-assignments?start_date=2024-01-01&end_date=2024-01-31')
        ->assertRedirect('/login');
});
