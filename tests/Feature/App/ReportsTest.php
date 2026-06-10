<?php

namespace Tests\Feature\App;

use App\Models\Location;
use App\Models\Report;
use App\Models\Shift;
use App\Models\ShiftUser;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Tags\Tag;
use Tests\TestCase;
use Tests\Traits\ExtraFunctions;
use Tests\Traits\MakesTags;

class ReportsTest extends TestCase
{
    use RefreshDatabase;
    use ExtraFunctions;
    use MakesTags;

    public function test_user_receives_correct_reports(): void
    {
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
    }

    public function test_user_can_submit_report_with_tags(): void
    {
        $user = User::factory()->enabled()->male()->create();
        $tag  = Tag::findOrCreate('test_tag', 'reports');

        $shift = Shift::factory()->everyDay9am()->for(Location::factory())->create();

        $this->assertDatabaseCount('reports', 0);

        ShiftUser::factory()
            ->state(['shift_date' => '2023-01-01'])
            ->for($shift, 'shift')
            ->for($user, 'user')
            ->create();

        $reportData = [
            'shift_date'          => '2023-01-01',
            'shift_id'            => $shift->getKey(),
            'start_time'          => '09:00:00',
            'shift_was_cancelled' => false,
            'placements_count'    => 2,
            'videos_count'        => 3,
            'requests_count'      => 4,
            'comments'            => 'A test comment',
            'tags'                => [$tag->id],
        ];

        $this->actingAs($user)->postJson('/save-report', $reportData);

        $this->assertDatabaseCount('reports', 1);
        $report = Report::first();
        $this->assertEquals($reportData['shift_date'], $report->shift_date);
        $this->assertEquals($reportData['shift_id'], $report->shift_id);
        $this->assertEquals($reportData['shift_was_cancelled'], $report->shift_was_cancelled);
        $this->assertEquals($reportData['placements_count'], $report->placements_count);
        $this->assertEquals($reportData['videos_count'], $report->videos_count);
        $this->assertEquals($reportData['requests_count'], $report->requests_count);
        $this->assertEquals($reportData['comments'], $report->comments);
        $this->assertEquals($reportData['tags'][0], $report->tags->first()->id);
    }

    public function test_validate_sister_cannot_submit_report_if_brother_only_is_specified(): void
    {
        $user = User::factory()->enabled()->female()->create();
        $tag  = Tag::findOrCreate('test_tag', 'reports');

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
            'shift_date'          => '2023-01-01',
            'shift_id'            => $shift->getKey(),
            'start_time'          => '09:00:00',
            'shift_was_cancelled' => false,
            'placements_count'    => 2,
            'videos_count'        => 3,
            'requests_count'      => 4,
            'comments'            => 'A test comment',
            'tags'                => [$tag->id],
        ];

        $response = $this->actingAs($user)->postJson('/save-report', $reportData);
        $response->assertStatus(422);
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_validate_sister_can_submit_report_if_brother_only_is_not_specified(): void
    {
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
    }

    public function test_validate_sister_can_be_prompted_to_submit_report_if_brother_only_is_not_specified(): void
    {
        $user = User::factory()->enabled()->female()->create();
        $tag  = Tag::findOrCreate('test_tag', 'reports');

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
            'shift_date'          => '2023-01-01',
            'shift_id'            => $shift->getKey(),
            'start_time'          => '09:00:00',
            'shift_was_cancelled' => false,
            'placements_count'    => 2,
            'videos_count'        => 3,
            'requests_count'      => 4,
            'comments'            => 'A test comment',
            'tags'                => [$tag->id],
        ];

        $response = $this->actingAs($user)->postJson('/save-report', $reportData);
        $response->assertStatus(422);
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_shift_on_date_should_be_fail_if_user_is_not_on_shift_date_or_start_time_is_wrong(): void
    {
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
            'shift_date'          => '2023-01-02',
            'shift_id'            => $shift->getKey(),
            'start_time'          => '09:00:00',
            'shift_was_cancelled' => false,
            'placements_count'    => 2,
            'videos_count'        => 3,
            'requests_count'      => 4,
            'comments'            => 'A test comment',
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
    }

    public function test_user_can_retrieve_all_tags(): void
    {
        $user = User::factory()->enabled()->create();
        $tags = $this->makeTags(5);

        $this->assertDatabaseCount('tags', 5);

        $this->actingAs($user)
            ->getJson("/get-report-tags")
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
            ->assertJsonPath('4.order_column', 5)
        ;
    }

}
