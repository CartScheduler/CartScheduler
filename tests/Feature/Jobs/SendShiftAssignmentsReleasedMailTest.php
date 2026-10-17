<?php

use App\Enums\DBPeriod;
use App\Jobs\SendShiftAssignmentsReleasedMail;
use App\Mail\ShiftAssignmentsReleased;
use App\Models\Location;
use App\Models\Shift;
use App\Models\ShiftUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Mail;
use Tests\Traits\SetConfig;

uses(RefreshDatabase::class);

uses(SetConfig::class);

beforeEach(function () {
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');
});

test('records notifications only after mail is sent', function () {
    [$user, $shiftUser] = releasedMailAssignmentFixture();

    Mail::fake();

    makeReleasedMailJob($user, [$shiftUser->id])->handle();

    Mail::assertSent(ShiftAssignmentsReleased::class, 1);
    $this->assertDatabaseCount('shift_assignment_notifications', 1);
    $this->assertDatabaseHas('shift_assignment_notifications', [
        'shift_user_id' => $shiftUser->id,
    ]);
});

test('does not record notifications when sending fails', function () {
    [$user, $shiftUser] = releasedMailAssignmentFixture();

    $pending = $this->mock(PendingMail::class);
    $pending->shouldReceive('send')->once()->andThrow(new RuntimeException('Throttled'));
    Mail::shouldReceive('to')->once()->with($user->email)->andReturn($pending);

    try {
        makeReleasedMailJob($user, [$shiftUser->id])->handle();
        $this->fail('Expected sending to throw');
    } catch (RuntimeException $exception) {
        $this->assertSame('Throttled', $exception->getMessage());
    }

    $this->assertDatabaseCount('shift_assignment_notifications', 0);
});

test('retry does not send a second email after a successful send', function () {
    [$user, $shiftUser] = releasedMailAssignmentFixture();

    Mail::fake();
    $job = makeReleasedMailJob($user, [$shiftUser->id]);

    $job->handle();
    $job->handle();

    Mail::assertSent(ShiftAssignmentsReleased::class, 1);
    $this->assertDatabaseCount('shift_assignment_notifications', 1);
});

/**
 * @return array{0: User, 1: ShiftUser}
 */
function releasedMailAssignmentFixture(): array
{
    $user = User::factory()->userRoleUser()->enabled()->create();
    $location = Location::factory()->create();
    $shift = Shift::factory()->everyDay9am()->for($location)->create();
    $user->attachShiftOnDate($shift, '2023-02-14');

    $shiftUser = ShiftUser::query()->where('user_id', $user->id)->firstOrFail();

    return [$user, $shiftUser];
}

/**
 * @param  list<int>  $shiftUserIds
 */
function makeReleasedMailJob(User $user, array $shiftUserIds): SendShiftAssignmentsReleasedMail
{
    return new SendShiftAssignmentsReleasedMail(
        $user,
        collect([
            [
                'date' => 'Tuesday, 14 February 2023',
                'start_time' => '9:00 AM',
                'end_time' => '11:00 AM',
                'location' => 'Town Square',
                'other_volunteers' => [],
            ],
        ]),
        [
            'start' => '13 February 2023',
            'end' => '19 February 2023',
            'label' => '13 February 2023 – 19 February 2023',
        ],
        $shiftUserIds,
    );
}
