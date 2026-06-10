<?php

namespace Tests\Feature\Integration\Actions;

use App\Actions\GetOutstandingReportCount;
use App\Models\Location;
use App\Models\Report;
use App\Models\Shift;
use App\Models\ShiftUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetOutstandingReportCountTest extends TestCase
{
    use RefreshDatabase;

    private GetOutstandingReportCount $getOutstandingReportCount;

    protected function setUp(): void
    {
        $this->getOutstandingReportCount = new GetOutstandingReportCount();
        parent::setUp();
    }

    public function test_outstanding_reports_count_query_is_returning_correct_count(): void
    {
        $location = Location::factory()
            ->state(['max_volunteers' => 3, 'requires_brother' => true])
            ->has(Shift::factory()->everyDay9am())
            ->create();

        /** @var \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, User>> $users */
        $users = User::factory()
            ->enabled()
            ->sequence(['gender' => 'male'], ['gender' => 'female'])
            ->count(5)
            ->create();

        $dateRange = collect();
        $users->take(3)
            ->each(fn(User $user) => $dateRange
                ->push(
                    [
                        'shift_date' => '2023-05-11',
                        'shift_id'   => $location->shifts[0]->id,
                        'user_id'    => $user->id,
                    ],
                    [
                        'shift_date' => '2023-05-13',
                        'shift_id'   => $location->shifts[0]->id,
                        'user_id'    => $user->id,
                    ],
                )
            );


        ShiftUser::factory()
            ->forEachSequence(...$dateRange->toArray())
            ->create();

        $this->travelTo('2023-05-01');
        $reports = $this->getOutstandingReportCount->execute();
        $this->assertSame(0, $reports);

        $this->travelTo('2023-05-11');
        $reports = $this->getOutstandingReportCount->execute();
        $this->assertSame(1, $reports);

        $report                           = new Report();
        $report->shift_id                 = $location->shifts[0]->id;
        $report->report_submitted_user_id = $users[0]->id;
        $report->shift_date               = '2023-05-11';
        $report->save();

        $reports = $this->getOutstandingReportCount->execute();
        $this->assertSame(0, $reports);

        $this->travelTo('2023-05-13');
        $reports = $this->getOutstandingReportCount->execute();
        $this->assertSame(1, $reports);
    }

    public function test_outstanding_reports_count_updates_correctly(): void
    {
        $location = Location::factory()
            ->state(['max_volunteers' => 3, 'requires_brother' => true])
            ->has(Shift::factory()->everyDay9am())
            ->has(Shift::factory()->everyDay1230pm())
            ->create();

        /** @var \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, User>> $users */
        $users = User::factory()
            ->enabled()
            ->sequence(['gender' => 'male'], ['gender' => 'female'])
            ->count(5)
            ->create();

        $dateRange = collect();

        $users->take(3)
            ->each(fn(User $user) => $dateRange
                ->push(
                    [
                        'shift_date' => '2023-05-14',
                        'shift_id'   => $location->shifts[0]->id,
                        'user_id'    => $user->id,
                    ],
                    [
                        'shift_date' => '2023-05-14',
                        'shift_id'   => $location->shifts[1]->id,
                        'user_id'    => $user->id,
                    ],
                )
            );

        ShiftUser::factory()
            ->forEachSequence(...$dateRange->toArray())
            ->create();

        $this->travelTo('2023-05-14');
        $reports = $this->getOutstandingReportCount->execute();
        $this->assertSame(2, $reports);

        $report                           = new Report();
        $report->shift_id                 = $location->shifts[0]->id;
        $report->report_submitted_user_id = $users[0]->id;
        $report->shift_date               = '2023-05-14';
        $report->save();

        $this->travelTo('2023-05-14');
        $reports = $this->getOutstandingReportCount->execute();
        $this->assertSame(1, $reports);
    }
}
