<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use DatePeriod;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShiftUserSeeder extends Seeder
{
    private array $dates;

    public function run(): void
    {
        $this->generateDates();

        $locations = Location::with('shifts')->where('is_enabled', true)->get();
        DB::beginTransaction();

        try {

            $locations->each(/**
             * @throws \Exception
             */ function (Location $location) {
                $maxUsers = $location->max_volunteers;
                $shifts   = $location->shifts;
                /** @var \App\Models\Shift $shift */
                foreach ($shifts as $shift) {
                    $dates = $this->mapDates($shift);

                    foreach ($dates as $date) {
                        $alreadyMappedUsers = collect();
                        for ($i = 0; $i < $maxUsers; $i++) {
                            if (($i === $maxUsers - 1) && random_int(0, 1) === 1) {
                                break; // this allows for the possibility of one user to be left out
                            }

                            $userId = User::inRandomOrder()->first()->id;
                            if ($alreadyMappedUsers->search($userId) !== false) {
                                // user has already been mapped to this shift
                                --$i;
                                continue;
                            }

                            $alreadyMappedUsers->push($userId);
                            $shift->users()->attach($userId, ['shift_date' => $date]);
                        }
                    }
                }
            });
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function mapDates(Shift $shift): Collection
    {
        $dates = collect();
        if ($shift->day_monday) {
            $dates = $dates->concat($this->dates['monday']);
        }
        if ($shift->day_tuesday) {
            $dates = $dates->concat($this->dates['tuesday']);
        }
        if ($shift->day_wednesday) {
            $dates = $dates->concat($this->dates['wednesday']);
        }
        if ($shift->day_thursday) {
            $dates = $dates->concat($this->dates['thursday']);
        }
        if ($shift->day_friday) {
            $dates = $dates->concat($this->dates['friday']);
        }
        if ($shift->day_saturday) {
            $dates = $dates->concat($this->dates['saturday']);
        }
        if ($shift->day_sunday) {
            $dates = $dates->concat($this->dates['sunday']);
        }

        return $dates;
    }

    private function generateDates(): void
    {
        $days  = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $dates = [];
        foreach ($days as $day) {
            $dates[$day] = collect(new DatePeriod(
                Carbon::parse("first $day of this month"),
                CarbonInterval::week(),
                Carbon::parse("last $day of next month")
            ))->map(static fn($date) => $date->format('Y-m-d'))->toArray();
        }
        $this->dates = $dates;
    }
}
