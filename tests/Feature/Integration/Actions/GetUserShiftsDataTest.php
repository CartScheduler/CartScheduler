<?php

use App\Actions\GetUserShiftsData;
use App\Data\UserShiftData;
use App\Models\Location;
use App\Models\Shift;
use App\Models\ShiftUser;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->getUserShiftsData = new GetUserShiftsData;
});

test('user shifts action is returning correct data', function () {
    $locations = Location::factory()
        ->state(['max_volunteers' => 4])
        ->count(2)
        ->has(
            Shift::factory()
                ->sequence(['available_from' => '2023-10-01', 'available_to' => '2023-10-31'], [])
                ->everyDay9am()
        )
        ->create();

    $locations->load('shifts');

    $user = User::factory()->enabled()->create();
    $users = User::factory()->count(10)->enabled()->create();

    $dateRange = collect();
    collect(CarbonPeriod::create('2023-10-01', '2023-10-31')->toArray())->random(4)
        ->map(function (Carbon $date, $index) use ($dateRange, $locations, $user, $users) {
            $user2 = $users->random();
            $shiftId = $index % 2 === 0 ? $locations[0]->shifts[0]->id : $locations[1]->shifts[0]->id;
            $dateRange->push(
                [
                    'shift_date' => $date->format('Y-m-d'),
                    'shift_id' => $shiftId,
                    'user_id' => $user->id,
                ],
                [
                    'shift_date' => $date->format('Y-m-d'),
                    'shift_id' => $shiftId,
                    'user_id' => $user2->id,
                ],
                [
                    'shift_date' => $date->format('Y-m-d'),
                    'shift_id' => $shiftId,
                    'user_id' => $users->whereNotIn('id', $user2->id)->random()->id,
                ]
            );
        });

    ShiftUser::factory()
        ->forEachSequence(...$dateRange->toArray())
        ->create();

    $this->travelTo('2023-10-01T01:00:00');

    $userShifts = $this->getUserShiftsData->execute('2023-10-01', '2023-10-31', $user);
    expect($userShifts)->toHaveCount(4);
    $userShiftIterator = $userShifts->getIterator();
    $index = 0;

    while ($userShiftIterator->valid()) {
        $userShift = $userShiftIterator->current();
        $dateKey = $userShiftIterator->key();
        $location = $index++ % 2 === 0 ? $locations[0] : $locations[1];
        $shift = $location->shifts[0];

        /** @var UserShiftData $userShiftData */
        $userShiftData = $userShift->get($shift->getKey())->get(0);

        expect($userShifts)->toHaveKey($dateKey);
        expect($userShiftData->volunteer_id)->toBe($user->id);
        expect($userShiftData->shift_date->toDateString())->toBe($dateKey);
        expect(Carbon::parse($dateKey)->isSameDay($userShiftData->shift_date))->toBeTrue();
        expect($userShiftData->shift_id)->toBe($shift->id);
        expect($userShiftData->start_time)->toBe('09:00:00');
        expect($userShiftData->location_id)->toBe($location->getKey());
        expect($userShiftData->max_volunteers)->toBe($location->max_volunteers);
        expect($userShiftData->available_from)->toBe($shift->available_from);
        expect($userShiftData->available_to)->toBe($shift->available_to);

        $userShiftIterator->next();
    }
});
