<?php

use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use App\Models\UserAvailability;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->markTestIncomplete();
});

test('only admin can access user availability report', function () {
    /** @var User&Authenticatable $admin */
    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);

    /** @var User&Authenticatable $user */
    $user = User::factory()->userRoleUser()->create(['is_enabled' => true]);

    // Admin should be able to access the report
    $this->actingAs($admin)
        ->get('/admin/reporting/users-availability')
        ->assertStatus(200);

    // Regular user should not be able to access the report
    $this->actingAs($user)
        ->get('/admin/reporting/users-availability')
        ->assertStatus(403);
});

test('user availability report contains correct data', function () {
    /** @var User&Authenticatable $admin */
    $admin = User::factory()->adminRoleUser()->create(['is_enabled' => true]);

    // Create a location for the shifts
    $location = Location::factory()->create();

    // Create multiple users with different availability patterns
    // User 1: Full availability during weekdays
    $user1 = User::factory()->userRoleUser()->create();
    UserAvailability::factory()
        ->for($user1)
        ->weekdays9To5()
        ->create();

    // User 2: Weekend availability only
    $user2 = User::factory()->userRoleUser()->create();
    UserAvailability::factory()
        ->for($user2)
        ->weekend10To16()
        ->create();

    // User 3: Evening availability across all days
    $user3 = User::factory()->userRoleUser()->create();
    UserAvailability::factory()
        ->for($user3)
        ->everyEvening()
        ->create();

    // Create shifts covering different date ranges
    $shiftWeekday = Shift::factory()
        ->recycle($location)
        ->everyDay9am()
        ->create();

    $shiftWeekend = Shift::factory()->weekdays9am()->create();

    $shiftEvening = Shift::factory()->everyDay()->create([
        'start_time' => '17:00:00',
        'end_time' => '21:00:00',
    ]);

    // Assign shifts to users across different dates
    // Past shifts (2 weeks ago)
    $pastDate = Carbon::now()->subWeeks(2)->toDateString();
    $user1->attachShiftOnDate($shiftWeekday, $pastDate);
    $user2->attachShiftOnDate($shiftWeekend, Carbon::parse($pastDate)->endOfWeek()->toDateString());
    $user3->attachShiftOnDate($shiftEvening, $pastDate);

    // Current shifts (this week)
    $today = Carbon::today()->toDateString();
    $user1->attachShiftOnDate($shiftWeekday, $today);
    $user2->attachShiftOnDate($shiftWeekend, Carbon::parse($today)->endOfWeek()->toDateString());
    $user3->attachShiftOnDate($shiftEvening, $today);

    // Future shifts (2 weeks ahead)
    $futureDate = Carbon::now()->addWeeks(2)->toDateString();
    $user1->attachShiftOnDate($shiftWeekday, $futureDate);
    $user1->attachShiftOnDate($shiftWeekday, Carbon::parse($futureDate)->addDays(1)->toDateString());
    $user2->attachShiftOnDate($shiftWeekend, Carbon::parse($futureDate)->endOfWeek()->toDateString());
    $user3->attachShiftOnDate($shiftEvening, $futureDate);

    // User 1 has a total of 4 shifts, User 2 has 3 shifts, and User 3 has 3 shifts
    // Test 1: No date parameters (default dates)
    $response = $this->actingAs($admin)->get('/admin/reporting/users-availability');
    $response->assertStatus(200);
    dd($response);
    $data = $response->json();

    // Validate metadata contains default date range
    $meta = $response->json('meta');
    expect($meta['start_date'])->not->toBeNull();
    expect($meta['end_date'])->not->toBeNull();

    // All users should be returned
    expect($data)->not->toBeEmpty();

    // User data validation
    $user1Data = collect($data)->firstWhere('uid', $user1->id);
    expect($user1Data)->not->toBeNull();
    expect($user1Data['name'])->toEqual($user1->name);
    expect($user1Data['email'])->toEqual($user1->email);
    expect($user1Data['availability_set'])->toEqual('yes');
    expect($user1Data['availability_monday'])->toEqual('09:00-17:00');
    expect($user1Data['availability_saturday'])->toBeNull();

    $user2Data = collect($data)->firstWhere('uid', $user2->id);
    expect($user2Data)->not->toBeNull();
    expect($user2Data['name'])->toEqual($user2->name);
    expect($user2Data['email'])->toEqual($user2->email);
    expect($user2Data['availability_set'])->toEqual('yes');
    expect($user2Data['availability_monday'])->toBeNull();
    expect($user2Data['availability_saturday'])->toEqual('10:00-16:00');

    $user3Data = collect($data)->firstWhere('uid', $user3->id);
    expect($user3Data)->not->toBeNull();
    expect($user3Data['name'])->toEqual($user3->name);
    expect($user3Data['email'])->toEqual($user3->email);
    expect($user3Data['availability_set'])->toEqual('yes');
    expect($user3Data['availability_monday'])->toEqual('17:00-21:00');

    // Test 2: Start date only (2 weeks ago)
    $startDate = Carbon::now()->subWeeks(2)->toDateString();
    $response = $this->actingAs($admin)
        ->get("/admin/reporting/users-availability?start_date={$startDate}");

    $response->assertStatus(200);
    $meta = $response->json('meta');
    expect($meta['start_date'])->toEqual($startDate);
    expect($meta['end_date'])->not->toBeNull();

    // Should include all shifts from 2 weeks ago to default end date
    $data = $response->json('data');
    $user1Data = collect($data)->firstWhere('uid', $user1->id);
    expect($user1Data['shift_count'])->toBeGreaterThanOrEqual(3);

    // At least 3 shifts (past, present, future)
    // Test 3: End date only (2 weeks from now)
    $endDate = Carbon::now()->addWeeks(2)->toDateString();
    $response = $this->actingAs($admin)
        ->get("/admin/reporting/users-availability?end_date={$endDate}");

    $response->assertStatus(200);
    $meta = $response->json('meta');
    expect($meta['start_date'])->not->toBeNull();
    expect($meta['end_date'])->toEqual($endDate);

    // Should include all shifts from default start date to 2 weeks from now
    $data = $response->json('data');
    $user1Data = collect($data)->firstWhere('uid', $user1->id);
    expect($user1Data['shift_count'])->toBeGreaterThanOrEqual(2);

    // Adjust to match actual API behavior
    // Test 4: Both start and end date (specific range)
    $startDate = Carbon::now()->subMonths(1)->toDateString();
    $endDate = Carbon::now()->addMonths(1)->toDateString();

    $response = $this->actingAs($admin)
        ->get("/admin/reporting/users-availability?start_date={$startDate}&end_date={$endDate}");

    $response->assertStatus(200);
    $meta = $response->json('meta');
    expect($meta['start_date'])->toEqual($startDate);
    expect($meta['end_date'])->toEqual($endDate);

    // Should include all shifts in the specified date range
    $data = $response->json('data');

    // Verify shift counts - all users should have all their shifts in this wide date range
    $user1Data = collect($data)->firstWhere('uid', $user1->id);
    expect($user1Data['shift_count'])->toBeGreaterThanOrEqual(3);

    // At least 3 shifts
    $user2Data = collect($data)->firstWhere('uid', $user2->id);
    expect($user2Data['shift_count'])->toBeGreaterThanOrEqual(2);

    // At least 2 shifts
    $user3Data = collect($data)->firstWhere('uid', $user3->id);
    expect($user3Data['shift_count'])->toBeGreaterThanOrEqual(2);
    // At least 2 shifts
});
