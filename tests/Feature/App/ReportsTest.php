<?php

use App\Models\Location;
use App\Models\Report;
use App\Models\Shift;
use App\Models\ShiftUser;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Tags\Tag;
use Tests\Traits\ExtraFunctions;
use Tests\Traits\MakesTags;

uses(ExtraFunctions::class);

uses(MakesTags::class);

uses(RefreshDatabase::class);

test('user receives correct reports', function () {
    $startDate = CarbonImmutable::createFromTimeString('2023-01-24 00:00:00');

    $users = User::factory()->count(3)->enabled()->male()->create();

    $dates = collect([
        ['shift_date' => '2023-01-01'],
        ['shift_date' => '2023-01-05'],
        ['shift_date' => '2023-01-06'],
        ['shift_date' => '2023-01-09'],
        ['shift_date' => '2023-01-11'],
        ['shift_date' => '2023-01-15'],
        ['shift_date' => '2023-01-20'],
        ['shift_date' => '2023-01-25'],
    ]);

    $shift = Shift::factory()->everyDay9am()->for(Location::factory()->requiresBrother())->create();
    foreach ($users as $user) {
        ShiftUser::factory()
            ->count($dates->count())
            ->sequence(...$dates->toArray())
            ->for($shift, 'shift')
            ->for($user, 'user')
            ->create();
    }

    $this->travelTo($startDate->midDay());
    $this->actingAs($users[0])->getJson('/outstanding-reports')
        ->assertJsonCount(7)
        ->assertJsonPath('0.shift_date', '2023-01-01')
        ->assertJsonPath('1.shift_date', '2023-01-05')
        ->assertJsonPath('2.shift_date', '2023-01-06')
        ->assertJsonPath('3.shift_date', '2023-01-09')
        ->assertJsonPath('4.shift_date', '2023-01-11')
        ->assertJsonPath('5.shift_date', '2023-01-15')
        ->assertJsonPath('6.shift_date', '2023-01-20');

    Report::factory()
        ->count(4)
        ->sequence(...$dates->toArray())
        ->for($shift)
        ->for($users[0])
        ->create();

    $this->actingAs($users[0])->getJson('/outstanding-reports')
        ->assertJsonCount(3)
        ->assertJsonPath('0.shift_date', '2023-01-11')
        ->assertJsonPath('1.shift_date', '2023-01-15')
        ->assertJsonPath('2.shift_date', '2023-01-20');

    $this->travelTo($startDate->setDay(25));
    $this->actingAs($users[0])->getJson('/outstanding-reports')
        ->assertJsonCount(4)
        ->assertJsonPath('0.shift_date', '2023-01-11')
        ->assertJsonPath('1.shift_date', '2023-01-15')
        ->assertJsonPath('2.shift_date', '2023-01-20')
        ->assertJsonPath('3.shift_date', '2023-01-25');
});

test('user can submit report with tags', function () {
    $user = User::factory()->enabled()->male()->create();
    $tag = Tag::findOrCreate('test_tag', 'reports');

    $shift = Shift::factory()->everyDay9am()->for(Location::factory())->create();

    $this->assertDatabaseCount('reports', 0);

    ShiftUser::factory()
        ->state(['shift_date' => '2023-01-01'])
        ->for($shift, 'shift')
        ->for($user, 'user')
        ->create();

    $reportData = [
        'shift_date' => '2023-01-01',
        'shift_id' => $shift->getKey(),
        'start_time' => '09:00:00',
        'shift_was_cancelled' => false,
        'placements_count' => 2,
        'videos_count' => 3,
        'requests_count' => 4,
        'comments' => 'A test comment',
        'tags' => [$tag->id],
    ];

    $this->actingAs($user)->postJson('/save-report', $reportData);

    $this->assertDatabaseCount('reports', 1);
    $report = Report::first();
    expect($report->shift_date)->toEqual($reportData['shift_date']);
    expect($report->shift_id)->toEqual($reportData['shift_id']);
    expect($report->shift_was_cancelled)->toEqual($reportData['shift_was_cancelled']);
    expect($report->placements_count)->toEqual($reportData['placements_count']);
    expect($report->videos_count)->toEqual($reportData['videos_count']);
    expect($report->requests_count)->toEqual($reportData['requests_count']);
    expect($report->comments)->toEqual($reportData['comments']);
    expect($report->tags->first()->id)->toEqual($reportData['tags'][0]);
});

test('validate sister cannot submit report if brother only is specified', function () {
    $user = User::factory()->enabled()->female()->create();
    $tag = Tag::findOrCreate('test_tag', 'reports');

    $shift = Shift::factory()
        ->everyDay9am()
        ->for(Location::factory()->state(['requires_brother' => true]))
        ->create();

    $this->assertDatabaseCount('reports', 0);

    ShiftUser::factory()
        ->state(['shift_date' => '2023-01-01'])
        ->for($shift, 'shift')
        ->for($user, 'user')
        ->create();

    $reportData = [
        'shift_date' => '2023-01-01',
        'shift_id' => $shift->getKey(),
        'start_time' => '09:00:00',
        'shift_was_cancelled' => false,
        'placements_count' => 2,
        'videos_count' => 3,
        'requests_count' => 4,
        'comments' => 'A test comment',
        'tags' => [$tag->id],
    ];

    $response = $this->actingAs($user)->postJson('/save-report', $reportData);
    $response->assertStatus(422);
    $this->assertDatabaseCount('reports', 0);
});

test('validate sister can submit report if brother only is not specified', function () {
    $startDate = CarbonImmutable::createFromTimeString('2023-01-24 00:00:00');

    $users = User::factory()->count(3)->enabled()->female()->create();

    $dates = collect([
        ['shift_date' => '2023-01-01'],
        ['shift_date' => '2023-01-05'],
        ['shift_date' => '2023-01-06'],
        ['shift_date' => '2023-01-09'],
        ['shift_date' => '2023-01-11'],
        ['shift_date' => '2023-01-15'],
        ['shift_date' => '2023-01-20'],
        ['shift_date' => '2023-01-25'],
    ]);

    $shift = Shift::factory()
        ->everyDay9am()
        ->for(
            Location::factory()
                ->allPublishers()
        )
        ->create();

    foreach ($users as $user) {
        ShiftUser::factory()
            ->count($dates->count())
            ->sequence(...$dates->toArray())
            ->for($shift, 'shift')
            ->for($user, 'user')
            ->create();
    }

    $this->travelTo($startDate->midDay());
    $this->actingAs($users[0])
        ->getJson('/outstanding-reports')
        ->assertJsonCount(7)
        ->assertJsonPath('0.shift_date', '2023-01-01')
        ->assertJsonPath('1.shift_date', '2023-01-05')
        ->assertJsonPath('2.shift_date', '2023-01-06')
        ->assertJsonPath('3.shift_date', '2023-01-09')
        ->assertJsonPath('4.shift_date', '2023-01-11')
        ->assertJsonPath('5.shift_date', '2023-01-15')
        ->assertJsonPath('6.shift_date', '2023-01-20');
});

test('validate sister can be prompted to submit report if brother only is not specified', function () {
    $user = User::factory()->enabled()->female()->create();
    $tag = Tag::findOrCreate('test_tag', 'reports');

    $shift = Shift::factory()
        ->everyDay9am()
        ->for(Location::factory()->state(['requires_brother' => true]))
        ->create();

    $this->assertDatabaseCount('reports', 0);

    ShiftUser::factory()
        ->state(['shift_date' => '2023-01-01'])
        ->for($shift, 'shift')
        ->for($user, 'user')
        ->create();

    $reportData = [
        'shift_date' => '2023-01-01',
        'shift_id' => $shift->getKey(),
        'start_time' => '09:00:00',
        'shift_was_cancelled' => false,
        'placements_count' => 2,
        'videos_count' => 3,
        'requests_count' => 4,
        'comments' => 'A test comment',
        'tags' => [$tag->id],
    ];

    $response = $this->actingAs($user)->postJson('/save-report', $reportData);
    $response->assertStatus(422);
    $this->assertDatabaseCount('reports', 0);
});

test('shift on date should be fail if user is not on shift date or start time is wrong', function () {
    $user = User::factory()->enabled()->create();

    $shift = Shift::factory()
        ->everyDay9am()
        ->for(Location::factory()->allPublishers())
        ->create();

    $this->assertDatabaseCount('reports', 0);

    ShiftUser::factory()
        ->state(['shift_date' => '2023-01-01'])
        ->for($shift, 'shift')
        ->for($user, 'user')
        ->create();

    $reportData = [
        'shift_date' => '2023-01-02',
        'shift_id' => $shift->getKey(),
        'start_time' => '09:00:00',
        'shift_was_cancelled' => false,
        'placements_count' => 2,
        'videos_count' => 3,
        'requests_count' => 4,
        'comments' => 'A test comment',
    ];

    $this->actingAs($user)
        ->postJson('/save-report', $reportData)
        ->assertUnprocessable()
        ->assertContainsStringIgnoringCase('message', 'does not match a shift');
    $this->assertDatabaseCount('reports', 0);

    $reportData['start_time'] = '08:00:00';
    $this->actingAs($user)
        ->postJson('/save-report', $reportData)
        ->assertUnprocessable()
        ->assertContainsStringIgnoringCase('message', 'does not match a shift');
    $this->assertDatabaseCount('reports', 0);
});

test('user cannot submit a report for a shift they were not on', function () {
    $rostered = User::factory()->enabled()->male()->create();
    $stranger = User::factory()->enabled()->male()->create();

    $shift = Shift::factory()->everyDay9am()->for(Location::factory()->allPublishers())->create();

    // Only the rostered volunteer has an assignment row for this shift.
    ShiftUser::factory()
        ->state(['shift_date' => '2023-01-01'])
        ->for($shift, 'shift')
        ->for($rostered, 'user')
        ->create();

    $this->assertDatabaseCount('reports', 0);

    $this->actingAs($stranger)
        ->postJson('/save-report', [
            'shift_date' => '2023-01-01',
            'shift_id' => $shift->getKey(),
            'start_time' => '09:00:00',
            'shift_was_cancelled' => false,
            'placements_count' => 2,
            'videos_count' => 3,
            'requests_count' => 4,
            'comments' => 'I was never here',
        ])
        ->assertUnprocessable()
        ->assertContainsStringIgnoringCase('message', 'does not match a shift');

    // A shift accepts one report, so a forged one would also have locked
    // out the volunteer who actually worked it.
    $this->assertDatabaseCount('reports', 0);
});

test('rostered user can still submit when someone else is on the same shift', function () {
    $user = User::factory()->enabled()->male()->create();
    $associate = User::factory()->enabled()->male()->create();

    $shift = Shift::factory()->everyDay9am()->for(Location::factory()->allPublishers())->create();

    foreach ([$user, $associate] as $volunteer) {
        ShiftUser::factory()
            ->state(['shift_date' => '2023-01-01'])
            ->for($shift, 'shift')
            ->for($volunteer, 'user')
            ->create();
    }

    // Scoping the lookup to the reporting user must not stop a genuine
    // shared shift being reported, nor lose the associate from the metadata.
    $this->actingAs($user)
        ->postJson('/save-report', [
            'shift_date' => '2023-01-01',
            'shift_id' => $shift->getKey(),
            'start_time' => '09:00:00',
            'shift_was_cancelled' => false,
            'placements_count' => 1,
            'videos_count' => 0,
            'requests_count' => 0,
            'comments' => 'Shared shift',
        ]);

    $this->assertDatabaseCount('reports', 1);
    $report = Report::first();
    expect($report->report_submitted_user_id)->toBe($user->id);
    expect($report->metadata['associates'][0]['id'])->toBe($associate->id);
});

test('user can retrieve all tags', function () {
    $user = User::factory()->enabled()->create();
    $tags = $this->makeTags(5);

    $this->assertDatabaseCount('tags', 5);

    $this->actingAs($user)
        ->getJson('/get-report-tags')
        ->assertOk()
        ->assertJsonCount(5)
        ->assertJsonPath('0.id', $tags[0]->id)
        ->assertJsonPath('0.name', $tags[0]->name)
        ->assertJsonPath('0.order_column', $tags[0]->order_column)
        ->assertJsonPath('0.order_column', 1)
        ->assertJsonPath('1.id', $tags[1]->id)
        ->assertJsonPath('1.name', $tags[1]->name)
        ->assertJsonPath('1.order_column', $tags[1]->order_column)
        ->assertJsonPath('1.order_column', 2)
        ->assertJsonPath('2.id', $tags[2]->id)
        ->assertJsonPath('2.name', $tags[2]->name)
        ->assertJsonPath('2.order_column', $tags[2]->order_column)
        ->assertJsonPath('2.order_column', 3)
        ->assertJsonPath('3.id', $tags[3]->id)
        ->assertJsonPath('3.name', $tags[3]->name)
        ->assertJsonPath('3.order_column', 4)
        ->assertJsonPath('4.id', $tags[4]->id)
        ->assertJsonPath('4.name', $tags[4]->name)
        ->assertJsonPath('4.order_column', 5);
});
