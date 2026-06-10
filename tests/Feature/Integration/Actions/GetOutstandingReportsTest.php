<?php

namespace Tests\Feature\Integration\Actions;

use App\Actions\GetOutstandingReports;
use App\Data\OutstandingReportsData;
use App\Models\Location;
use App\Models\Report;
use App\Models\Shift;
use App\Models\ShiftUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use stdClass;
use Tests\TestCase;

class GetOutstandingReportsTest extends TestCase
{
    use RefreshDatabase;

    private GetOutstandingReports $getOutstandingReports;

    protected function setUp(): void
    {
        parent::setUp();
        $this->getOutstandingReports = new GetOutstandingReports();
    }

    public function test_outstanding_reports_query_is_returning_correct_data(): void
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
                    [
                        'shift_date' => '2023-05-15',
                        'shift_id'   => $location->shifts[0]->id,
                        'user_id'    => $user->id,
                    ]
                )
            );

        ShiftUser::factory()
            ->forEachSequence(...$dateRange->toArray())
            ->create();

        $this->travelTo('2023-05-01');
        $reports = $this->getOutstandingReports->execute($users[0]);
        $this->assertCount(0, $reports);
        $reports = $this->getOutstandingReports->execute($users[1]);
        $this->assertCount(0, $reports);

        $this->travelTo('2023-05-11');
        $reports = $this->getOutstandingReports->execute($users[0]);
        $this->assertCount(1, $reports);
        $this->assertSame('2023-05-11', $reports[0]->shift_date);

        $this->travelTo('2023-05-13');
        $reports = $this->getOutstandingReports->execute($users[0]);
        $this->assertCount(2, $reports);
        $this->assertSame('2023-05-11', $reports[0]->shift_date);
        $this->assertSame('2023-05-13', $reports[1]->shift_date);

        $this->travelTo('2023-05-15');
        $reports = $this->getOutstandingReports->execute($users[0]);
        $this->assertCount(3, $reports);
        $this->assertSame('2023-05-11', $reports[0]->shift_date);
        $this->assertSame('2023-05-13', $reports[1]->shift_date);
        $this->assertSame('2023-05-15', $reports[2]->shift_date);
        // User[2] is a female. At this stage, female users will see all reports from this function call
        $reports = $this->getOutstandingReports->execute($users[1]);
        $this->assertCount(3, $reports);

        $report                           = new Report();
        $report->shift_id                 = $location->shifts[0]->id;
        $report->report_submitted_user_id = $users[0]->id;
        $report->shift_date               = '2023-05-11';
        $report->save();

        $reports = $this->getOutstandingReports->execute($users[0]);
        $this->assertCount(2, $reports);
        $this->assertCount(2, $reports->filter(fn(OutstandingReportsData $report) => $report->shift_date !== '2023-05-11'));
    }

    public function test_outstanding_reports_shown_when_shift_is_not_fulfilled(): void
    {
        $location = Location::factory()
            ->state(['min_volunteers' => 3, 'max_volunteers' => 5])
            ->has(Shift::factory()->everyDay9am())
            ->create();

        /** @var \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, User>> $users */
        $users = User::factory()
            ->enabled()
            ->male()
            ->count(2)
            ->create();

        ShiftUser::factory()
            ->state([
                'shift_date' => '2023-05-11',
                'shift_id'   => $location->shifts[0]->id,
                'user_id'    => $users[0]->id,
            ])
            ->create();

        $this->travelTo('2023-05-11');
        $reports = $this->getOutstandingReports->execute($users[0]);
        $this->assertCount(1, $reports);
        $this->assertSame('2023-05-11', $reports[0]->shift_date);
    }


    public function test_relevant_users_received_correct_reports(): void
    {
        $locations = Location::factory()
            ->count(2)
            ->state(['max_volunteers' => 3, 'requires_brother' => true])
            ->has(Shift::factory()->everyDay9am())
            ->create();

        /** @var \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, User>> $users */
        $users = User::factory()
            ->enabled()
            ->male()
            ->count(5)
            ->create();

        $dateRange = collect([
            [
                'shift_date' => '2023-05-11',
                'shift_id'   => $locations[0]->shifts[0]->id,
                'user_id'    => $users[0]->id,
            ],
            [
                'shift_date' => '2023-05-13',
                'shift_id'   => $locations[1]->shifts[0]->id,
                'user_id'    => $users[1]->id,
            ]
        ]);

        ShiftUser::factory()
            ->forEachSequence(...$dateRange->toArray())
            ->create();

        $this->travelTo('2023-05-10');
        $reports = $this->getOutstandingReports->execute($users[0]);
        $this->assertCount(0, $reports);
        $reports = $this->getOutstandingReports->execute($users[1]);
        $this->assertCount(0, $reports);

        $this->travelTo('2023-05-11');
        $reports = $this->getOutstandingReports->execute($users[0]);
        $this->assertCount(1, $reports);
        $this->assertSame('2023-05-11', $reports[0]->shift_date);
        $this->assertCount(0, $this->getOutstandingReports->execute($users[1]));

        $this->travelTo('2023-05-13');
        $reports = $this->getOutstandingReports->execute($users[0]);
        $this->assertCount(1, $reports);
        $this->assertSame('2023-05-11', $reports[0]->shift_date);
        $reports = $this->getOutstandingReports->execute($users[1]);
        $this->assertCount(1, $reports);
        $this->assertSame('2023-05-13', $reports[0]->shift_date);
    }

    public function test_null_user_returns_all_reports(): void
    {
        $location = Location::factory()
            ->state(['max_volunteers' => 3, 'requires_brother' => true])
            ->has(Shift::factory()->everyDay9am())
            ->create();

        /** @var \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, User>> $users */
        $users = User::factory()
            ->enabled()
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
                    [
                        'shift_date' => '2023-05-15',
                        'shift_id'   => $location->shifts[0]->id,
                        'user_id'    => $user->id,
                    ]
                )
            );

        ShiftUser::factory()
            ->forEachSequence(...$dateRange->toArray())
            ->create();

        $this->assertDatabaseCount('shift_user', 9);

        $this->travelTo('2023-05-01');
        $reports = $this->getOutstandingReports->execute();
        $this->assertCount(0, $reports);

        $this->travelTo('2023-05-11');
        $reports = $this->getOutstandingReports->execute();
        // even though there technically should be 3 reports, the query groups them by the date and only returns one
        $this->assertCount(1, $reports);
        $this->assertCount(1, $reports->filter(fn(OutstandingReportsData $report) => $report->shift_date === '2023-05-11'));

        $this->travelTo('2023-05-13');
        $reports = $this->getOutstandingReports->execute();
        $this->assertCount(2, $reports);
        $this->assertCount(1, $reports->filter(fn(OutstandingReportsData $report) => $report->shift_date === '2023-05-11'));
        $this->assertCount(1, $reports->filter(fn(OutstandingReportsData $report) => $report->shift_date === '2023-05-13'));

        $this->travelTo('2023-05-15');
        $reports = $this->getOutstandingReports->execute();
        $this->assertCount(3, $reports);
        $this->assertCount(1, $reports->filter(fn(OutstandingReportsData $report) => $report->shift_date === '2023-05-11'));
        $this->assertCount(1, $reports->filter(fn(OutstandingReportsData $report) => $report->shift_date === '2023-05-13'));
        $this->assertCount(1, $reports->filter(fn(OutstandingReportsData $report) => $report->shift_date === '2023-05-15'));
    }}
