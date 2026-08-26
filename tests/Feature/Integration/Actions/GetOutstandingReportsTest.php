<?php

use App\Actions\GetOutstandingReports;
use App\Data\OutstandingReportsData;
use App\Models\Location;
use App\Models\Report;
use App\Models\Shift;
use App\Models\ShiftUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->getOutstandingReports = new GetOutstandingReports;
});

test('outstanding reports query is returning correct data', function () {
    $location = Location::factory()
        ->state(['max_volunteers' => 3, 'requires_brother' => true])
        ->has(Shift::factory()->everyDay9am())
        ->create();

    /** @var Collection<int, Collection<int, User>> $users */
    $users = User::factory()
        ->enabled()
        ->sequence(['gender' => 'male'], ['gender' => 'female'])
        ->count(5)
        ->create();

    $dateRange = collect();
    $users->take(3)
        ->each(fn (User $user) => $dateRange
            ->push(
                [
                    'shift_date' => '2023-05-11',
                    'shift_id' => $location->shifts[0]->id,
                    'user_id' => $user->id,
                ],
                [
                    'shift_date' => '2023-05-13',
                    'shift_id' => $location->shifts[0]->id,
                    'user_id' => $user->id,
                ],
                [
                    'shift_date' => '2023-05-15',
                    'shift_id' => $location->shifts[0]->id,
                    'user_id' => $user->id,
                ]
            )
        );

    ShiftUser::factory()
        ->forEachSequence(...$dateRange->toArray())
        ->create();

    $this->travelTo('2023-05-01');
    $reports = $this->getOutstandingReports->execute($users[0]);
    expect($reports)->toHaveCount(0);
    $reports = $this->getOutstandingReports->execute($users[1]);
    expect($reports)->toHaveCount(0);

    $this->travelTo('2023-05-11');
    $reports = $this->getOutstandingReports->execute($users[0]);
    expect($reports)->toHaveCount(1);
    expect($reports[0]->shift_date)->toBe('2023-05-11');

    $this->travelTo('2023-05-13');
    $reports = $this->getOutstandingReports->execute($users[0]);
    expect($reports)->toHaveCount(2);
    expect($reports[0]->shift_date)->toBe('2023-05-11');
    expect($reports[1]->shift_date)->toBe('2023-05-13');

    $this->travelTo('2023-05-15');
    $reports = $this->getOutstandingReports->execute($users[0]);
    expect($reports)->toHaveCount(3);
    expect($reports[0]->shift_date)->toBe('2023-05-11');
    expect($reports[1]->shift_date)->toBe('2023-05-13');
    expect($reports[2]->shift_date)->toBe('2023-05-15');

    // User[2] is a female. At this stage, female users will see all reports from this function call
    $reports = $this->getOutstandingReports->execute($users[1]);
    expect($reports)->toHaveCount(3);

    $report = new Report;
    $report->shift_id = $location->shifts[0]->id;
    $report->report_submitted_user_id = $users[0]->id;
    $report->shift_date = '2023-05-11';
    $report->save();

    $reports = $this->getOutstandingReports->execute($users[0]);
    expect($reports)->toHaveCount(2);
    expect($reports->filter(fn (OutstandingReportsData $report) => $report->shift_date !== '2023-05-11'))->toHaveCount(2);
});

test('outstanding reports shown when shift is not fulfilled', function () {
    $location = Location::factory()
        ->state(['min_volunteers' => 3, 'max_volunteers' => 5])
        ->has(Shift::factory()->everyDay9am())
        ->create();

    /** @var Collection<int, Collection<int, User>> $users */
    $users = User::factory()
        ->enabled()
        ->male()
        ->count(2)
        ->create();

    ShiftUser::factory()
        ->state([
            'shift_date' => '2023-05-11',
            'shift_id' => $location->shifts[0]->id,
            'user_id' => $users[0]->id,
        ])
        ->create();

    $this->travelTo('2023-05-11');
    $reports = $this->getOutstandingReports->execute($users[0]);
    expect($reports)->toHaveCount(1);
    expect($reports[0]->shift_date)->toBe('2023-05-11');
});

test('relevant users received correct reports', function () {
    $locations = Location::factory()
        ->count(2)
        ->state(['max_volunteers' => 3, 'requires_brother' => true])
        ->has(Shift::factory()->everyDay9am())
        ->create();

    /** @var Collection<int, Collection<int, User>> $users */
    $users = User::factory()
        ->enabled()
        ->male()
        ->count(5)
        ->create();

    $dateRange = collect([
        [
            'shift_date' => '2023-05-11',
            'shift_id' => $locations[0]->shifts[0]->id,
            'user_id' => $users[0]->id,
        ],
        [
            'shift_date' => '2023-05-13',
            'shift_id' => $locations[1]->shifts[0]->id,
            'user_id' => $users[1]->id,
        ],
    ]);

    ShiftUser::factory()
        ->forEachSequence(...$dateRange->toArray())
        ->create();

    $this->travelTo('2023-05-10');
    $reports = $this->getOutstandingReports->execute($users[0]);
    expect($reports)->toHaveCount(0);
    $reports = $this->getOutstandingReports->execute($users[1]);
    expect($reports)->toHaveCount(0);

    $this->travelTo('2023-05-11');
    $reports = $this->getOutstandingReports->execute($users[0]);
    expect($reports)->toHaveCount(1);
    expect($reports[0]->shift_date)->toBe('2023-05-11');
    expect($this->getOutstandingReports->execute($users[1]))->toHaveCount(0);

    $this->travelTo('2023-05-13');
    $reports = $this->getOutstandingReports->execute($users[0]);
    expect($reports)->toHaveCount(1);
    expect($reports[0]->shift_date)->toBe('2023-05-11');
    $reports = $this->getOutstandingReports->execute($users[1]);
    expect($reports)->toHaveCount(1);
    expect($reports[0]->shift_date)->toBe('2023-05-13');
});

test('null user returns all reports', function () {
    $location = Location::factory()
        ->state(['max_volunteers' => 3, 'requires_brother' => true])
        ->has(Shift::factory()->everyDay9am())
        ->create();

    /** @var Collection<int, Collection<int, User>> $users */
    $users = User::factory()
        ->enabled()
        ->count(5)
        ->create();

    $dateRange = collect();
    $users->take(3)
        ->each(fn (User $user) => $dateRange
            ->push(
                [
                    'shift_date' => '2023-05-11',
                    'shift_id' => $location->shifts[0]->id,
                    'user_id' => $user->id,
                ],
                [
                    'shift_date' => '2023-05-13',
                    'shift_id' => $location->shifts[0]->id,
                    'user_id' => $user->id,
                ],
                [
                    'shift_date' => '2023-05-15',
                    'shift_id' => $location->shifts[0]->id,
                    'user_id' => $user->id,
                ]
            )
        );

    ShiftUser::factory()
        ->forEachSequence(...$dateRange->toArray())
        ->create();

    $this->assertDatabaseCount('shift_user', 9);

    $this->travelTo('2023-05-01');
    $reports = $this->getOutstandingReports->execute();
    expect($reports)->toHaveCount(0);

    $this->travelTo('2023-05-11');
    $reports = $this->getOutstandingReports->execute();

    // even though there technically should be 3 reports, the query groups them by the date and only returns one
    expect($reports)->toHaveCount(1);
    expect($reports->filter(fn (OutstandingReportsData $report) => $report->shift_date === '2023-05-11'))->toHaveCount(1);

    $this->travelTo('2023-05-13');
    $reports = $this->getOutstandingReports->execute();
    expect($reports)->toHaveCount(2);
    expect($reports->filter(fn (OutstandingReportsData $report) => $report->shift_date === '2023-05-11'))->toHaveCount(1);
    expect($reports->filter(fn (OutstandingReportsData $report) => $report->shift_date === '2023-05-13'))->toHaveCount(1);

    $this->travelTo('2023-05-15');
    $reports = $this->getOutstandingReports->execute();
    expect($reports)->toHaveCount(3);
    expect($reports->filter(fn (OutstandingReportsData $report) => $report->shift_date === '2023-05-11'))->toHaveCount(1);
    expect($reports->filter(fn (OutstandingReportsData $report) => $report->shift_date === '2023-05-13'))->toHaveCount(1);
    expect($reports->filter(fn (OutstandingReportsData $report) => $report->shift_date === '2023-05-15'))->toHaveCount(1);
});
