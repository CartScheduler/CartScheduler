<?php

use App\Actions\GetAvailableShiftsCount;
use App\Models\Location;
use App\Models\Shift;
use App\Models\ShiftUser;
use App\Models\User;
use Database\Factories\Sequences\ShiftTimeSequence;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const LOCATION_DEFAULTS = [
    'requires_brother' => true,
    'min_volunteers' => 3,
    'max_volunteers' => 3,
];

beforeEach(function () {
    $this->getAvailableShiftsCount = new GetAvailableShiftsCount;
});

test('rostered volunteers are being calculated properly', function () {
    $users = User::factory()->count(5)->create(['is_enabled' => true]);

    Location::factory()
        ->count(4)
        ->has(
            Shift::factory()->everyDay9am()
        )
        ->create(LOCATION_DEFAULTS);

    $startDate = '2022-10-01';
    $endDate = '2022-10-31';

    $result = $this->getAvailableShiftsCount->execute($startDate, $endDate)->toArray();

    expect($result)->not->toBeEmpty();
    expect($result)->toHaveCount(31);
    expect($result)->toHaveKey($startDate);
    expect($result)->toHaveKey($endDate);
    $first = $result[$startDate];
    expect($first['volunteer_count'])->toEqual(0);
    expect($first['max_allowed'])->toEqual(12);
    expect($first['has_availability'])->toBeTrue();

    // Add 2 volunteers to the first shift and check the count
    $users->take(2)->each(fn (User $user) => ShiftUser::factory()->create([
        'user_id' => $user->id,
        'shift_id' => Shift::first()->id,
        'shift_date' => $startDate,
    ]));

    $result = $this->getAvailableShiftsCount->execute($startDate, $endDate)->toArray();
    $first = $result[$startDate];
    expect($first['volunteer_count'])->toEqual(2);
    expect($first['max_allowed'])->toEqual(12);
    expect($first['has_availability'])->toBeTrue();
});

test('inactive locations dont show', function () {
    buildLocationWithShiftsFromCallback(count: 4, sequence: fn (Sequence $sequence) => ['is_enabled' => $sequence->index === 3], tap: fn (Factory $factory) => $factory->state(new ShiftTimeSequence));

    $result = $this->getAvailableShiftsCount->execute('2022-10-01', '2022-10-31')->toArray();
    expect($result['2022-10-01']['max_allowed'])->toEqual(3);
});

test('inactive shifts dont show', function () {
    $location = buildLocationWithShiftsFromCallback(count: 3, sequence: fn (Sequence $sequence) => ['is_enabled' => $sequence->index !== 1], tap: fn (Factory $factory) => $factory->state(new ShiftTimeSequence));
    $shifts = $location->shifts;

    expect($shifts[0]->is_enabled)->toBeTrue();
    expect($shifts[1]->is_enabled)->toBeFalse();
    // just to be sure
    expect($shifts[2]->is_enabled)->toBeTrue();
    expect($shifts)->toHaveCount(3);

    $result = $this->getAvailableShiftsCount->execute('2022-10-01', '2022-10-31')->toArray();
    expect($result['2022-10-01']['max_allowed'])->toEqual(6);
});

test('only shifts between available dates show', function () {
    buildLocationWithShiftsFromArray(['start_time' => '09:00:00', 'end_time' => '12:00:00', 'is_enabled' => false], ['start_time' => '09:00:00', 'end_time' => '12:00:00', 'available_from' => '2022-10-10', 'available_to' => '2022-10-20'], ['start_time' => '12:00:00', 'end_time' => '15:00:00'], ['start_time' => '15:00:00', 'end_time' => '18:00:00']);

    $result = $this->getAvailableShiftsCount->execute('2022-10-01', '2022-10-31')->toArray();

    // Between 1oct and 9oct, 6 shifts are available
    expect($result['2022-10-05']['max_allowed'])->toEqual(6);

    // Between 10oct and 20oct, 9 shifts are available
    expect($result['2022-10-15']['max_allowed'])->toEqual(9);

    // After 20oct, only 6 shifts are available
    expect($result['2022-10-25']['max_allowed'])->toEqual(6);
});

test('shifts not available dont show', function () {
    buildLocationWithShiftsFromArray(['start_time' => '09:00:00', 'end_time' => '12:00:00', 'available_from' => '2022-10-15']);

    $startDate = '2022-10-01';
    $endDate = '2022-10-31';

    $result = $this->getAvailableShiftsCount->execute($startDate, $endDate)->toArray();

    expect($result['2022-10-01']['max_allowed'])->toEqual(0);
});

test('only shifts on available from show', function () {
    buildLocationWithShiftsFromArray(['start_time' => '09:00:00', 'end_time' => '12:00:00', 'available_from' => '2022-10-15']);

    $result = $this->getAvailableShiftsCount->execute('2022-10-01', '2022-10-31')->toArray();

    expect($result['2022-10-14']['max_allowed'])->toEqual(0);
    expect($result['2022-10-15']['max_allowed'])->toEqual(3);
});

test('only shifts after available from with no available to show', function () {
    buildLocationWithShiftsFromArray(['start_time' => '09:00:00', 'end_time' => '12:00:00', 'available_from' => '2022-09-01']);

    $result = $this->getAvailableShiftsCount->execute('2022-10-01', '2022-10-31')->toArray();

    expect($result['2022-10-15']['max_allowed'])->toEqual(3);
});

test('only shifts on available to show', function () {
    buildLocationWithShiftsFromArray(['start_time' => '09:00:00', 'end_time' => '12:00:00', 'available_to' => '2022-10-15']);

    $result = $this->getAvailableShiftsCount->execute('2022-10-01', '2022-10-31')->toArray();

    expect($result['2022-10-15']['max_allowed'])->toEqual(3);
    expect($result['2022-10-16']['max_allowed'])->toEqual(0);
});

test('only shifts before available to with no available from show', function () {
    buildLocationWithShiftsFromArray(['start_time' => '09:00:00', 'end_time' => '12:00:00', 'available_to' => '2022-11-15']);

    $result = $this->getAvailableShiftsCount->execute('2022-10-01', '2022-10-31')->toArray();

    expect($result['2022-10-15']['max_allowed'])->toEqual(3);
});

test('only shifts on day of week have availability', function () {
    buildLocationWithShiftsFromArray([
        'day_monday' => true,
        'day_tuesday' => true,
        'day_wednesday' => true,
        'day_thursday' => true,
        'day_friday' => true,
        'day_saturday' => false,
        'day_sunday' => false,
    ]);

    $result = $this->getAvailableShiftsCount->execute('2022-10-01', '2022-10-31')->toArray();
    expect($result['2022-10-01']['has_availability'])->toBeFalse();
    // Saturday
    expect($result['2022-10-02']['has_availability'])->toBeFalse();
    expect($result['2022-10-03']['has_availability'])->toBeTrue();
    expect($result['2022-10-04']['has_availability'])->toBeTrue();
    expect($result['2022-10-05']['has_availability'])->toBeTrue();
    expect($result['2022-10-06']['has_availability'])->toBeTrue();
    expect($result['2022-10-07']['has_availability'])->toBeTrue();

    expect($result['2022-10-08']['has_availability'])->toBeFalse();
    expect($result['2022-10-09']['has_availability'])->toBeFalse();
    expect($result['2022-10-10']['has_availability'])->toBeTrue();
    expect($result['2022-10-11']['has_availability'])->toBeTrue();
    expect($result['2022-10-12']['has_availability'])->toBeTrue();
    expect($result['2022-10-13']['has_availability'])->toBeTrue();
    expect($result['2022-10-14']['has_availability'])->toBeTrue();
    expect($result['2022-10-15']['has_availability'])->toBeFalse();
    expect($result['2022-10-16']['has_availability'])->toBeFalse();
});

function buildLocationWithShiftsFromArray(array ...$shifts): Location
{
    return Location::factory()
        ->has(
            Shift::factory()
                ->count(count($shifts))
                ->everyDay9am()
                ->sequence(...$shifts)
        )
        ->create(LOCATION_DEFAULTS);
}

function buildLocationWithShiftsFromCallback(int $count, callable $sequence, ?callable $tap = null): Location
{
    return Location::factory()
        ->has(
            Shift::factory()
                ->count($count)
                ->everyDay9am()
                ->when($tap !== null, $tap)
                ->sequence($sequence)
        )
        ->create(LOCATION_DEFAULTS);
}
