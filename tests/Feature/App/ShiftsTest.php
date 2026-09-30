<?php

use App\Enums\DBPeriod;
use App\Models\Location;
use App\Models\Shift;
use App\Models\ShiftUser;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\Traits\SetConfig;

uses(RefreshDatabase::class);

uses(SetConfig::class);

test('retrieve shifts over three weeks', function () {
    /*
     * February 2023
     * Mo Tu We Th Fr Sa Su
     *        1  2  3  4  5 <- When today is 2nd, can't get shifts for the 27th
     *  6  7  8  9 10 11 12 <- When today is 6th, can't get shifts for the 27th until 2:00 PM
     * 13 14 15 16 17 18 19
     * 20 21 22 23 24 25 26
     * 27 28
     */
    $this->setConfig(3, DBPeriod::Week, false, 'MON', '14:00');

    $startDate = generateDBData('2023-02-02 12:30:00');

    $user = ShiftUser::with('user')->first()->user;

    $this->travelTo($startDate);
    $this->actingAs($user)->getJson("/shifts/{$startDate->addDay()->toDateString()}")
        ->assertSuccessful()
        ->assertJsonCount(1, 'shifts')
        ->assertJsonCount(25, 'freeShifts')
        ->assertJsonFragment(['maxDateReservation' => '2023-02-26'])
        ->assertJsonMissingPath('freeShifts.2023-02-01')
        ->assertJsonMissingPath('freeShifts.2023-02-27')
        ->assertJsonHasKeys('freeShifts', '2023-02-02', '2023-02-26');

    // Testing the new shift release before 2pm on Monday
    $this->travelTo($startDate->addDays(4)->setTimeFromTimeString('13:59:59'));
    $this->actingAs($user)->getJson("/shifts/{$startDate->addDays(4)->toDateString()}")
        ->assertJsonCount(0, 'shifts')
        ->assertJsonCount(21, 'freeShifts')
        ->assertJsonFragment(['maxDateReservation' => '2023-02-26'])
        ->assertJsonMissingPath('freeShifts.2023-02-05')
        ->assertJsonMissingPath('freeShifts.2023-02-27')
        ->assertJsonHasKeys('freeShifts', '2023-02-06', '2023-02-26');

    // Testing the new shift release after 2pm on Monday
    $this->travelTo($startDate->addDays(4)->setTimeFromTimeString('14:00:00'));
    $this->actingAs($user)->getJson("/shifts/{$startDate->addDays(4)->toDateString()}")
        ->assertJsonCount(0, 'shifts')
        ->assertJsonCount(28, 'freeShifts')
        ->assertJsonFragment(['maxDateReservation' => '2023-03-05'])
        ->assertJsonMissingPath('freeShifts.2023-02-05')
        ->assertJsonMissingPath('freeShifts.2023-03-06')
        ->assertJsonHasKeys('freeShifts', '2023-02-06', '2023-03-05');
});

test('one week only retrieve approved shifts after set time', function () {
    /*
     * February 2023
     * Mo Tu We Th Fr Sa Su
     *        1  2  3  4  5
     *  6  7  8  9 10 11 12 <- When today is 6th, can't get shifts for the 19th until 12:30 PM
     * 13 14 15 16 17 18 19
     * 20 21 22 23 24 25 26
     * 27 28
     */
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');

    $startDate = generateDBData('2023-02-02 12:30:00');

    $user = ShiftUser::with('user')->first()->user;
    $this->travelTo($startDate);
    $this->actingAs($user)->getJson("/shifts/{$startDate->addDay()->toDateString()}")
        ->assertJsonCount(1, 'shifts')
        ->assertJsonCount(11, 'freeShifts')
        ->assertJsonFragment(['maxDateReservation' => '2023-02-12'])
        ->assertJsonMissingPath('freeShifts.2023-02-01')
        ->assertJsonMissingPath('freeShifts.2023-02-13')
        ->assertJsonHasKeys('freeShifts', '2023-02-02', '2023-02-12');

    // Testing the new shift release before 12:30pm on Monday
    $this->travelTo($startDate->addDays(4)->setTimeFromTimeString('12:29:59'));
    $this->actingAs($user)->getJson("/shifts/{$startDate->addDays(4)->toDateString()}")
        ->assertJsonCount(0, 'shifts')
        ->assertJsonCount(7, 'freeShifts')
        ->assertJsonFragment(['maxDateReservation' => '2023-02-12'])
        ->assertJsonMissingPath('freeShifts.2023-02-05')
        ->assertJsonMissingPath('freeShifts.2023-02-13')
        ->assertJsonHasKeys('freeShifts', '2023-02-06', '2023-02-12');

    // Testing the new shift release after 12:30pm on Monday
    $this->travelTo($startDate->addDays(4)->setTimeFromTimeString('12:30:00'));
    $this->actingAs($user)->getJson("/shifts/{$startDate->addDays(4)->toDateString()}")
        ->assertJsonCount(0, 'shifts')
        ->assertJsonCount(14, 'freeShifts')
        ->assertJsonFragment(['maxDateReservation' => '2023-02-19'])
        ->assertJsonMissingPath('freeShifts.2023-02-05')
        ->assertJsonMissingPath('freeShifts.2023-02-20')
        ->assertJsonHasKeys('freeShifts', '2023-02-06', '2023-02-19');

    // Testing month crossover
    $this->travelTo($startDate->setDay(25)->midDay());
    $this->actingAs($user)->getJson("/shifts/{$startDate->setDay(25)->toDateString()}")
        ->assertJsonCount(0, 'shifts')
        ->assertJsonCount(9, 'freeShifts')
        ->assertJsonFragment(['maxDateReservation' => '2023-03-05'])
        ->assertJsonMissingPath('freeShifts.2023-02-24')
        ->assertJsonMissingPath('freeShifts.2023-03-06')
        ->assertJsonHasKeys('freeShifts', '2023-02-25', '2023-03-05');
});

test('user cannot retrieve shifts before today', function () {
    $this->setConfig(1, DBPeriod::Month, false, 'MON', '12:00');

    $startDate = generateDBData('2023-01-01 00:00:00');

    $user = ShiftUser::with('user')->first()->user;

    $this->travelTo($startDate);

    // request data from the previous month
    $this->actingAs($user)->getJson("/shifts/{$startDate->subMonth()->setDay(15)->toDateString()}")
        ->assertJsonCount(31, 'freeShifts')
        ->assertJsonFragment(['maxDateReservation' => '2023-01-31'])
        ->assertJsonHasKeys('freeShifts', '2023-01-01', '2023-01-31')
        ->assertJsonMissingPath('freeShifts.2022-12-31')
        ->assertJsonMissingPath('freeShifts.2022-12-30');
});

test('user cannot retrieve shifts after allowed shift timeframe', function () {
    $this->setConfig(1, DBPeriod::Month, false, 'MON', '12:00');

    $startDate = generateDBData('2023-01-01 00:00:00');

    $user = ShiftUser::with('user')->first()->user;

    $this->travelTo($startDate);

    // request data for the next month - which is out of bounds
    $this->actingAs($user)
        ->getJson("/shifts/{$startDate->addMonth()->setDay(15)->toDateString()}")
        ->assertJsonCount(0, 'freeShifts')
        ->assertJsonFragment(['maxDateReservation' => '2023-01-31']);
});

test('available shifts released daily for month', function () {
    $this->setConfig(1, DBPeriod::Month, true, 'SUN', '00:00');

    $startDate = generateDBData('2023-01-15 00:00:00');

    $user = ShiftUser::with('user')->first()->user;

    $this->travelTo($startDate);
    $this->actingAs($user)->getJson("/shifts/{$startDate->toDateString()}")
        ->assertJsonCount(31, 'freeShifts')
        ->assertJsonFragment(['maxDateReservation' => '2023-02-14'])
        ->assertJsonHasKeys('freeShifts', '2023-01-15', '2023-02-14');
});

test('available shifts released daily for month after time', function () {
    $this->setConfig(1, DBPeriod::Month, true, 'SUN', '12:00');

    $startDate = generateDBData('2023-01-15 11:59:59');

    $user = ShiftUser::with('user')->first()->user;

    $this->travelTo($startDate);
    $this->actingAs($user)->getJson("/shifts/{$startDate->toDateString()}")
        ->assertJsonCount(30, 'freeShifts')
        ->assertJsonFragment(['maxDateReservation' => '2023-02-13'])
        ->assertJsonHasKeys('freeShifts', '2023-01-15', '2023-02-13');

    $this->travelTo($startDate->setTimeFromTimeString('12:00:00'));
    $this->actingAs($user)->getJson("/shifts/{$startDate->toDateString()}")
        ->assertJsonCount(31, 'freeShifts')
        ->assertJsonFragment(['maxDateReservation' => '2023-02-14'])
        ->assertJsonHasKeys('freeShifts', '2023-01-15', '2023-02-14');
});

test('available shifts released daily for two months', function () {
    $this->setConfig(2, DBPeriod::Month, true, 'SUN', '00:00');

    $startDate = generateDBData('2023-01-15 00:00:00');

    $user = ShiftUser::with('user')->first()->user;

    $this->travelTo($startDate);
    $this->actingAs($user)->getJson("/shifts/{$startDate->toDateString()}")
        ->assertJsonCount(59, 'freeShifts')
        ->assertJsonFragment(['maxDateReservation' => '2023-03-14'])
        ->assertJsonHasKeys('freeShifts', '2023-01-15', '2023-03-14');
});

test('available shifts released daily for week', function () {
    $this->setConfig(1, DBPeriod::Week, true, 'SUN', '00:00');

    $startDate = generateDBData('2023-01-15 00:00:01');

    $user = ShiftUser::with('user')->first()->user;

    $this->travelTo($startDate);
    $this->actingAs($user)->getJson("/shifts/{$startDate->toDateString()}")
        ->assertJsonCount(7, 'freeShifts')
        ->assertJsonFragment(['maxDateReservation' => '2023-01-21'])
        ->assertJsonHasKeys('freeShifts', '2023-01-15', '2023-01-21');
});

test('available shifts released daily for one week after time', function () {
    $this->setConfig(1, DBPeriod::Week, true, 'SUN', '12:00');

    $startDate = generateDBData('2023-01-15 11:59:59');

    $user = ShiftUser::with('user')->first()->user;

    $this->travelTo($startDate);
    $this->actingAs($user)->getJson("/shifts/{$startDate->toDateString()}")
        ->assertJsonCount(6, 'freeShifts')
        ->assertJsonFragment(['maxDateReservation' => '2023-01-20'])
        ->assertJsonHasKeys('freeShifts', '2023-01-15', '2023-01-20');

    $this->travelTo($startDate->setTimeFromTimeString('12:00:00'));
    $this->actingAs($user)->getJson("/shifts/{$startDate->toDateString()}")
        ->assertJsonCount(7, 'freeShifts')
        ->assertJsonFragment(['maxDateReservation' => '2023-01-21'])
        ->assertJsonHasKeys('freeShifts', '2023-01-15', '2023-01-21');
});

test('available shifts released daily for three weeks', function () {
    $this->setConfig(3, DBPeriod::Week, true, 'SUN', '00:00');

    $startDate = generateDBData('2023-01-15 11:00:01');

    $user = ShiftUser::with('user')->first()->user;

    $this->travelTo($startDate);
    $this->actingAs($user)->getJson("/shifts/{$startDate->toDateString()}")
        ->assertJsonCount(21, 'freeShifts')
        ->assertJsonFragment(['maxDateReservation' => '2023-02-04'])
        ->assertJsonHasKeys('freeShifts', '2023-01-15', '2023-02-04');
});

test('available shifts released once per month', function () {
    $this->setConfig(1, DBPeriod::Month, false, 'MON', '00:00');

    $startDate = generateDBData('2023-01-25 00:00:00');

    $user = ShiftUser::with('user')->first()->user;

    $this->travelTo($startDate);
    $this->actingAs($user)->getJson("/shifts/{$startDate->toDateString()}")
        ->assertJsonCount(35, 'freeShifts')
        ->assertJsonFragment(['maxDateReservation' => '2023-02-28'])
        ->assertJsonHasKeys('freeShifts', '2023-01-25', '2023-02-28');
});

test('available shifts released once per month at a time', function () {
    $this->setConfig(1, DBPeriod::Month, false, 'MON', '12:00');

    $startDate = generateDBData('2023-02-01 11:59:59');

    $user = ShiftUser::with('user')->first()->user;

    $this->travelTo($startDate);
    $this->actingAs($user)->getJson("/shifts/{$startDate->toDateString()}")
        ->assertJsonCount(28, 'freeShifts')
        ->assertJsonFragment(['maxDateReservation' => '2023-02-28'])
        ->assertJsonHasKeys('freeShifts', '2023-02-01', '2023-02-28');

    // Move to midday which should open up another month's worth of shifts
    $this->travelTo($startDate->midDay());
    $this->actingAs($user)->getJson("/shifts/{$startDate->toDateString()}")
        ->assertJsonCount(59, 'freeShifts')
        ->assertJsonFragment(['maxDateReservation' => '2023-03-31'])
        ->assertJsonHasKeys('freeShifts', '2023-02-01', '2023-03-31');

    $this->travelTo($startDate->setDay(15)->midDay());
    $this->actingAs($user)->getJson("/shifts/{$startDate->toDateString()}")
        ->assertJsonCount(45, 'freeShifts')
        ->assertJsonFragment(['maxDateReservation' => '2023-03-31'])
        ->assertJsonMissingPath('freeShifts.2023-02-01')
        ->assertJsonMissingPath('freeShifts.2023-02-14')
        ->assertJsonHasKeys('freeShifts', '2023-02-15', '2023-03-31');
});

test('available shifts released beginning of month for three month duration', function () {
    $this->setConfig(3, DBPeriod::Month, false, 'MON', '00:00');

    $startDate = generateDBData('2023-01-25 00:00:00');

    $user = ShiftUser::with('user')->first()->user;

    $this->travelTo($startDate);
    $this->actingAs($user)->getJson("/shifts/{$startDate->toDateString()}")
        ->assertJsonCount(96, 'freeShifts')
        ->assertJsonFragment(['maxDateReservation' => '2023-04-30'])
        ->assertJsonHasKeys('freeShifts', '2023-01-25', '2023-04-30');
});

test('not enabled user cannot see shifts', function () {
    $this->setConfig(3, DBPeriod::Month, false, 'MON', '00:00');

    $startDate = generateDBData('2023-01-25 00:00:00');

    $user = User::factory()->male()->state(['is_enabled' => false])->create();

    $this->travelTo($startDate);
    $this->actingAs($user)->getJson("/shifts/{$startDate->toDateString()}")
        ->assertUnauthorized();
});

test('user sends invalid date to get shifts', function () {
    $this->setConfig(2, DBPeriod::Week, true, 'MON', '00:00');

    /** @var Collection<int, Collection<int, User>> $users */
    $users = User::factory()->count(4)->enabled()->create()->chunk(2);

    $location = Location::factory()
        ->allPublishers()
        ->has(
            Shift::factory()
                ->everyDay9am()
                ->hasAttached($users->first(), ['shift_date' => '2023-01-03'])
                ->hasAttached($users->last(), ['shift_date' => '2023-01-04'])
        )
        ->has(Shift::factory()
            ->everyDay1230pm()
            ->hasAttached($users->first(), ['shift_date' => '2023-01-03'])
            ->hasAttached($users->last(), ['shift_date' => '2023-01-04'])
        )
        ->create();

    $this->travelTo('2023-01-03 09:00:00');

    $this->actingAs($users->first()->first())
        ->getJson('/shifts/2023-05-55') // This date doesn't exist
        ->assertOk()
        // Should fail silently and return values for today
        ->assertJsonCount(0, 'freeShifts')
        ->assertJsonCount(0, 'shifts')
        ->assertJsonCount(0, 'locations');
});

test('user sends date beyond max reservation date to get shifts', function () {
    $this->setConfig(2, DBPeriod::Week, true, 'MON', '00:00');

    /** @var Collection<int, Collection<int, User>> $users */
    $users = User::factory()->count(4)->enabled()->create()->chunk(2);

    Location::factory()
        ->allPublishers()
        ->has(
            Shift::factory()
                ->everyDay9am()
                ->hasAttached($users->first(), ['shift_date' => '2023-01-03'])
                ->hasAttached($users->last(), ['shift_date' => '2023-01-04'])
        )
        ->has(Shift::factory()
            ->everyDay1230pm()
            ->hasAttached($users->first(), ['shift_date' => '2023-01-03'])
            ->hasAttached($users->last(), ['shift_date' => '2023-01-04'])
        )
        ->create();

    $this->travelTo('2023-01-03 09:00:00');

    $this->actingAs($users->first()->first())
        ->getJson('/shifts/2023-02-01') // This date is beyond the max reservation date
        ->assertOk()
        // First day should be today, not the date sent
        ->assertJsonCount(0, 'freeShifts')
        ->assertJsonCount(0, 'shifts')
        ->assertJsonCount(0, 'locations');
});

/**
 * @param  string  $timeString  Eg '2023-01-25 00:00:00'
 */
function generateDBData(string $timeString): CarbonImmutable
{
    $startDate = CarbonImmutable::createFromTimeString($timeString);

    Location::factory()
        ->state(['max_volunteers' => 3])
        ->has(Shift::factory()
            ->everyDay9am()
            ->hasAttached(
                User::factory()
                    ->userRoleUser()
                    ->count(3)
                    ->state(['is_enabled' => true])
                // Add shifts so the system works; it doesn't show any free shifts if there's no shift in the system
                , ['shift_date' => $startDate->toDateString()]
            )
        )
        ->create();

    return $startDate;
}
