<?php

namespace Tests\Feature\Integration\Actions;

use App\Actions\GetUserShiftsData;
use App\Models\Location;
use App\Models\Shift;
use App\Models\ShiftUser;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetUserShiftsDataTest extends TestCase
{
    use RefreshDatabase;

    private GetUserShiftsData $getUserShiftsData;

    protected function setUp(): void
    {
        $this->getUserShiftsData = new GetUserShiftsData();
        parent::setUp();
    }

    public function test_user_shifts_action_is_returning_correct_data(): void
    {
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

        $user  = User::factory()->enabled()->create();
        $users = User::factory()->count(10)->enabled()->create();

        $dateRange = collect();
        collect(CarbonPeriod::create('2023-10-01', '2023-10-31')->toArray())->random(4)
            ->map(function (Carbon $date, $index) use ($dateRange, $locations, $user, $users) {
                $user2   = $users->random();
                $shiftId = $index % 2 === 0 ? $locations[0]->shifts[0]->id : $locations[1]->shifts[0]->id;
                $dateRange->push(
                    [
                        'shift_date' => $date->format('Y-m-d'),
                        'shift_id'   => $shiftId,
                        'user_id'    => $user->id,
                    ],
                    [
                        'shift_date' => $date->format('Y-m-d'),
                        'shift_id'   => $shiftId,
                        'user_id'    => $user2->id,
                    ],
                    [
                        'shift_date' => $date->format('Y-m-d'),
                        'shift_id'   => $shiftId,
                        'user_id'    => $users->whereNotIn('id', $user2->id)->random()->id,
                    ]
                );
            });

        ShiftUser::factory()
            ->forEachSequence(...$dateRange->toArray())
            ->create();

        $this->travelTo('2023-10-01T01:00:00');

        $userShifts = $this->getUserShiftsData->execute('2023-10-01', '2023-10-31', $user);
        $this->assertCount(4, $userShifts);
        $userShiftIterator = $userShifts->getIterator();
        $index             = 0;

        while ($userShiftIterator->valid()) {
            $userShift = $userShiftIterator->current();
            $dateKey   = $userShiftIterator->key();
            $location  = $index++ % 2 === 0 ? $locations[0] : $locations[1];
            $shift     = $location->shifts[0];

            /** @var \App\Data\UserShiftData $userShiftData */
            $userShiftData = $userShift->get($shift->getKey())->get(0);

            $this->assertArrayHasKey($dateKey, $userShifts);
            $this->assertSame($user->id, $userShiftData->volunteer_id);
            $this->assertSame($dateKey, $userShiftData->shift_date->toDateString());
            $this->assertTrue(Carbon::parse($dateKey)->isSameDay($userShiftData->shift_date));
            $this->assertSame($shift->id, $userShiftData->shift_id);
            $this->assertSame('09:00:00', $userShiftData->start_time);
            $this->assertSame($location->getKey(), $userShiftData->location_id);
            $this->assertSame($location->max_volunteers, $userShiftData->max_volunteers);
            $this->assertSame($shift->available_from, $userShiftData->available_from);
            $this->assertSame($shift->available_to, $userShiftData->available_to);

            $userShiftIterator->next();
        }
    }
}
