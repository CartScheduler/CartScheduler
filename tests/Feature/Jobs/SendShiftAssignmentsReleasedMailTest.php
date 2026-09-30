<?php

namespace Tests\Feature\Jobs;

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
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\SetConfig;

class SendShiftAssignmentsReleasedMailTest extends TestCase
{
    use RefreshDatabase;
    use SetConfig;

    public function test_records_notifications_only_after_mail_is_sent(): void
    {
        [$user, $shiftUser] = $this->assignmentFixture();

        Mail::fake();

        $this->job($user, [$shiftUser->id])->handle();

        Mail::assertSent(ShiftAssignmentsReleased::class, 1);
        $this->assertDatabaseCount('shift_assignment_notifications', 1);
        $this->assertDatabaseHas('shift_assignment_notifications', [
            'shift_user_id' => $shiftUser->id,
        ]);
    }

    public function test_does_not_record_notifications_when_sending_fails(): void
    {
        [$user, $shiftUser] = $this->assignmentFixture();

        $pending = $this->mock(PendingMail::class);
        $pending->shouldReceive('send')->once()->andThrow(new RuntimeException('Throttled'));
        Mail::shouldReceive('to')->once()->with($user->email)->andReturn($pending);

        try {
            $this->job($user, [$shiftUser->id])->handle();
            $this->fail('Expected sending to throw');
        } catch (RuntimeException $exception) {
            $this->assertSame('Throttled', $exception->getMessage());
        }

        $this->assertDatabaseCount('shift_assignment_notifications', 0);
    }

    public function test_retry_does_not_send_a_second_email_after_a_successful_send(): void
    {
        [$user, $shiftUser] = $this->assignmentFixture();

        Mail::fake();
        $job = $this->job($user, [$shiftUser->id]);

        $job->handle();
        $job->handle();

        Mail::assertSent(ShiftAssignmentsReleased::class, 1);
        $this->assertDatabaseCount('shift_assignment_notifications', 1);
    }

    /**
     * @return array{0: User, 1: ShiftUser}
     */
    private function assignmentFixture(): array
    {
        $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');

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
    private function job(User $user, array $shiftUserIds): SendShiftAssignmentsReleasedMail
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
}
