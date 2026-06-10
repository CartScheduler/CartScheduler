<?php

namespace Tests\Feature\Integration\Actions;

use App\Actions\GetAvailableShiftsCount;
use App\Models\Location;
use App\Models\Shift;
use App\Models\ShiftUser;
use App\Models\User;
use Database\Factories\Sequences\ShiftTimeSequence;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetAvailableShiftsCountTest extends TestCase
{
    use RefreshDatabase;

    private const LOCATION_DEFAULTS = [
        'requires_brother' => true,
        'min_volunteers'   => 3,
        'max_volunteers'   => 3,
    ];

    private GetAvailableShiftsCount $getAvailableShiftsCount;

    protected function setUp(): void
    {
        $this->getAvailableShiftsCount = new GetAvailableShiftsCount();
        parent::setUp();
    }

    public function test_rostered_volunteers_are_being_calculated_properly(): void
    {
        $users = User::factory()->count(5)->create(['is_enabled' => true]);

        Location::factory()
            ->count(4)
            ->has(
                Shift::factory()->everyDay9am()
            )
            ->create(self::LOCATION_DEFAULTS);

        $startDate = '2022-10-01';
        $endDate   = '2022-10-31';

        $result = $this->getAvailableShiftsCount->execute($startDate, $endDate)->toArray();

        $this->assertNotEmpty($result);
        $this->assertCount(31, $result);
        $this->assertArrayHasKey($startDate, $result);
        $this->assertArrayHasKey($endDate, $result);
        $first = $result[$startDate];
        $this->assertEquals(0, $first['volunteer_count']);
        $this->assertEquals(12, $first['max_allowed']);
        $this->assertTrue($first['has_availability']);

        // Add 2 volunteers to the first shift and check the count
        $users->take(2)->each(fn(User $user) => ShiftUser::factory()->create([
            'user_id'    => $user->id,
            'shift_id'   => Shift::first()->id,
            'shift_date' => $startDate,
        ]));

        $result = $this->getAvailableShiftsCount->execute($startDate, $endDate)->toArray();
        $first  = $result[$startDate];
        $this->assertEquals(2, $first['volunteer_count']);
        $this->assertEquals(12, $first['max_allowed']);
        $this->assertTrue($first['has_availability']);
    }

    public function test_inactive_locations_dont_show(): void
    {
        $this->buildLocationWithShiftsFromCallback(
            count: 4,
            sequence: fn(Sequence $sequence) => ['is_enabled' => $sequence->index === 3],
            tap: fn(Factory $factory) => $factory->state(new ShiftTimeSequence()),
        );

        $result = $this->getAvailableShiftsCount->execute('2022-10-01', '2022-10-31')->toArray();
        $this->assertEquals(3, $result['2022-10-01']['max_allowed']);
    }

    public function test_inactive_shifts_dont_show(): void
    {
        $location = $this->buildLocationWithShiftsFromCallback(
            count: 3,
            sequence: fn(Sequence $sequence) => ['is_enabled' => $sequence->index !== 1],
            tap: fn(Factory $factory) => $factory->state(new ShiftTimeSequence()),
        );
        $shifts   = $location->shifts;

        $this->assertTrue($shifts[0]->is_enabled);
        $this->assertFalse($shifts[1]->is_enabled); // just to be sure
        $this->assertTrue($shifts[2]->is_enabled);
        $this->assertCount(3, $shifts);

        $result = $this->getAvailableShiftsCount->execute('2022-10-01', '2022-10-31')->toArray();
        $this->assertEquals(6, $result['2022-10-01']['max_allowed']);
    }

    public function test_only_shifts_between_available_dates_show(): void
    {
        $this->buildLocationWithShiftsFromArray(
            ['start_time' => '09:00:00', 'end_time' => '12:00:00', 'is_enabled' => false],
            ['start_time' => '09:00:00', 'end_time' => '12:00:00', 'available_from' => '2022-10-10', 'available_to' => '2022-10-20'],
            ['start_time' => '12:00:00', 'end_time' => '15:00:00'],
            ['start_time' => '15:00:00', 'end_time' => '18:00:00'],
        );

        $result = $this->getAvailableShiftsCount->execute('2022-10-01', '2022-10-31')->toArray();
        // Between 1oct and 9oct, 6 shifts are available
        $this->assertEquals(6, $result['2022-10-05']['max_allowed']);
        // Between 10oct and 20oct, 9 shifts are available
        $this->assertEquals(9, $result['2022-10-15']['max_allowed']);
        // After 20oct, only 6 shifts are available
        $this->assertEquals(6, $result['2022-10-25']['max_allowed']);
    }

    public function test_shifts_not_available_dont_show(): void
    {
        $this->buildLocationWithShiftsFromArray(
            ['start_time' => '09:00:00', 'end_time' => '12:00:00', 'available_from' => '2022-10-15']
        );

        $startDate = '2022-10-01';
        $endDate   = '2022-10-31';

        $result = $this->getAvailableShiftsCount->execute($startDate, $endDate)->toArray();

        $this->assertEquals(0, $result['2022-10-01']['max_allowed']);
    }

    public function test_only_shifts_on_available_from_show(): void
    {
        $this->buildLocationWithShiftsFromArray(
            ['start_time' => '09:00:00', 'end_time' => '12:00:00', 'available_from' => '2022-10-15'],
        );

        $result = $this->getAvailableShiftsCount->execute('2022-10-01', '2022-10-31')->toArray();

        $this->assertEquals(0, $result['2022-10-14']['max_allowed']);
        $this->assertEquals(3, $result['2022-10-15']['max_allowed']);
    }

    public function test_only_shifts_after_available_from_with_no_available_to_show(): void
    {
        $this->buildLocationWithShiftsFromArray(
            ['start_time' => '09:00:00', 'end_time' => '12:00:00', 'available_from' => '2022-09-01']
        );

        $result = $this->getAvailableShiftsCount->execute('2022-10-01', '2022-10-31')->toArray();

        $this->assertEquals(3, $result['2022-10-15']['max_allowed']);
    }

    public function test_only_shifts_on_available_to_show(): void
    {
        $this->buildLocationWithShiftsFromArray(
            ['start_time' => '09:00:00', 'end_time' => '12:00:00', 'available_to' => '2022-10-15'],
        );

        $result = $this->getAvailableShiftsCount->execute('2022-10-01', '2022-10-31')->toArray();

        $this->assertEquals(3, $result['2022-10-15']['max_allowed']);
        $this->assertEquals(0, $result['2022-10-16']['max_allowed']);
    }

    public function test_only_shifts_before_available_to_with_no_available_from_show(): void
    {
        $this->buildLocationWithShiftsFromArray(
            ['start_time' => '09:00:00', 'end_time' => '12:00:00', 'available_to' => '2022-11-15'],
        );

        $result = $this->getAvailableShiftsCount->execute('2022-10-01', '2022-10-31')->toArray();

        $this->assertEquals(3, $result['2022-10-15']['max_allowed']);

    }

    public function test_only_shifts_on_day_of_week_have_availability(): void
    {
        $this->buildLocationWithShiftsFromArray([
            'day_monday'    => true,
            'day_tuesday'   => true,
            'day_wednesday' => true,
            'day_thursday'  => true,
            'day_friday'    => true,
            'day_saturday'  => false,
            'day_sunday'    => false,
        ]);

        $result = $this->getAvailableShiftsCount->execute('2022-10-01', '2022-10-31')->toArray();
        $this->assertFalse($result['2022-10-01']['has_availability']); // Saturday
        $this->assertFalse($result['2022-10-02']['has_availability']);
        $this->assertTrue($result['2022-10-03']['has_availability']);
        $this->assertTrue($result['2022-10-04']['has_availability']);
        $this->assertTrue($result['2022-10-05']['has_availability']);
        $this->assertTrue($result['2022-10-06']['has_availability']);
        $this->assertTrue($result['2022-10-07']['has_availability']);

        $this->assertFalse($result['2022-10-08']['has_availability']);
        $this->assertFalse($result['2022-10-09']['has_availability']);
        $this->assertTrue($result['2022-10-10']['has_availability']);
        $this->assertTrue($result['2022-10-11']['has_availability']);
        $this->assertTrue($result['2022-10-12']['has_availability']);
        $this->assertTrue($result['2022-10-13']['has_availability']);
        $this->assertTrue($result['2022-10-14']['has_availability']);
        $this->assertFalse($result['2022-10-15']['has_availability']);
        $this->assertFalse($result['2022-10-16']['has_availability']);

    }

    private function buildLocationWithShiftsFromArray(array ...$shifts): Location
    {
        return Location::factory()
            ->has(
                Shift::factory()
                    ->count(count($shifts))
                    ->everyDay9am()
                    ->sequence(...$shifts)
            )
            ->create(self::LOCATION_DEFAULTS);
    }

    private function buildLocationWithShiftsFromCallback(int $count, callable $sequence, callable $tap = null): Location
    {
        return Location::factory()
            ->has(
                Shift::factory()
                    ->count($count)
                    ->everyDay9am()
                    ->when($tap !== null, $tap)
                    ->sequence($sequence)
            )
            ->create(self::LOCATION_DEFAULTS);
    }
}
