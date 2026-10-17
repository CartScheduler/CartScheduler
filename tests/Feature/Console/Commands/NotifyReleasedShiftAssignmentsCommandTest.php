<?php

use App\Actions\NotifyVolunteersOfReleasedShifts;
use App\Console\Commands\NotifyReleasedShiftAssignmentsCommand;
use App\Enums\DBPeriod;
use App\Jobs\SendShiftAssignmentsReleasedMail;
use App\Mail\ShiftAssignmentsReleased;
use App\Models\Location;
use App\Models\Shift;
use App\Models\ShiftAssignmentNotification;
use App\Models\ShiftUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Traits\SetConfig;

uses(RefreshDatabase::class);

uses(SetConfig::class);

test('weekly release sends one email per user for newly opened slice only', function () {
    /*
     * February 2023
     * Mo Tu We Th Fr Sa Su
     *        1  2  3  4  5
     *  6  7  8  9 10 11 12
     * 13 14 15 16 17 18 19 <- newly opened after Mon 6 at 12:30
     * 20 21 22 23 24 25 26
     */
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');

    $userWithNewShift = User::factory()->userRoleUser()->enabled()->create();
    $userWithOldShift = User::factory()->userRoleUser()->enabled()->create();
    $userWithNoShift = User::factory()->userRoleUser()->enabled()->create();

    $location = Location::factory()->create(['name' => 'Town Square']);
    $shift = Shift::factory()->everyDay9am()->for($location)->create();

    $userWithOldShift->attachShiftOnDate($shift, '2023-02-08');
    $userWithNewShift->attachShiftOnDate($shift, '2023-02-14');
    $userWithNewShift->attachShiftOnDate($shift, '2023-02-16');

    Mail::fake();

    $this->travelTo('2023-02-06 12:29:59');
    $this->assertSame(
        ['users_notified' => 0, 'assignments_notified' => 0],
        app(NotifyVolunteersOfReleasedShifts::class)->execute()
    );
    Mail::assertNothingSent();

    $this->travelTo('2023-02-06 12:30:01');
    $result = app(NotifyVolunteersOfReleasedShifts::class)->execute();

    expect($result)->toBe(['users_notified' => 1, 'assignments_notified' => 2]);
    Mail::assertSent(ShiftAssignmentsReleased::class, 1);
    Mail::assertSent(ShiftAssignmentsReleased::class, function (ShiftAssignmentsReleased $mail) use ($userWithNewShift) {
        return $mail->hasTo($userWithNewShift->email)
            && $mail->shifts->count() === 2
            && $mail->period === [
                'start' => '13 February 2023',
                'end' => '19 February 2023',
                'label' => '13 February 2023 – 19 February 2023',
            ];
    });
    Mail::assertNotSent(ShiftAssignmentsReleased::class, fn (ShiftAssignmentsReleased $mail) => $mail->hasTo($userWithOldShift->email));
    Mail::assertNotSent(ShiftAssignmentsReleased::class, fn (ShiftAssignmentsReleased $mail) => $mail->hasTo($userWithNoShift->email));
    $this->assertDatabaseCount('shift_assignment_notifications', 2);

    $this->assertSame(
        ['users_notified' => 0, 'assignments_notified' => 0],
        app(NotifyVolunteersOfReleasedShifts::class)->execute()
    );
    Mail::assertSent(ShiftAssignmentsReleased::class, 1);
});

test('monthly release notifies assignments in newly opened month', function () {
    $this->setConfig(1, DBPeriod::Month, false, null, '09:00');

    $user = User::factory()->userRoleUser()->enabled()->create();
    $location = Location::factory()->create(['name' => 'Hall']);
    $shift = Shift::factory()->everyDay9am()->for($location)->create();

    $user->attachShiftOnDate($shift, '2023-01-15');
    $user->attachShiftOnDate($shift, '2023-02-10');

    Mail::fake();

    $this->travelTo('2023-01-01 08:59:59');
    $this->assertSame(
        ['users_notified' => 0, 'assignments_notified' => 0],
        app(NotifyVolunteersOfReleasedShifts::class)->execute()
    );

    $this->travelTo('2023-01-01 09:00:01');
    $result = app(NotifyVolunteersOfReleasedShifts::class)->execute();

    expect($result)->toBe(['users_notified' => 1, 'assignments_notified' => 1]);
    Mail::assertSent(ShiftAssignmentsReleased::class, fn (ShiftAssignmentsReleased $mail) => $mail->shifts->count() === 1
        && str_contains($mail->shifts->first()['date'], '10 February 2023'));
});

test('daily release notifies assignments on newly opened day', function () {
    $this->setConfig(1, DBPeriod::Week, true, 'MON', '08:00');

    $user = User::factory()->userRoleUser()->enabled()->create();
    $location = Location::factory()->create();
    $shift = Shift::factory()->everyDay9am()->for($location)->create();

    $user->attachShiftOnDate($shift, '2023-01-09');
    $user->attachShiftOnDate($shift, '2023-01-08');

    Mail::fake();

    $this->travelTo('2023-01-03 07:59:59');
    $this->assertSame(
        ['users_notified' => 0, 'assignments_notified' => 0],
        app(NotifyVolunteersOfReleasedShifts::class)->execute()
    );

    $this->travelTo('2023-01-03 08:00:01');
    $result = app(NotifyVolunteersOfReleasedShifts::class)->execute();

    expect($result)->toBe(['users_notified' => 1, 'assignments_notified' => 1]);
    Mail::assertSent(ShiftAssignmentsReleased::class, fn (ShiftAssignmentsReleased $mail) => $mail->shifts->count() === 1
        && str_contains($mail->shifts->first()['date'], '9 January 2023'));
});

test('email includes other volunteers on the same shift', function () {
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');

    $recipient = User::factory()->userRoleUser()->enabled()->create([
        'name' => 'Alice Recipient',
        'mobile_phone' => '0411111111',
    ]);
    $partner = User::factory()->userRoleUser()->enabled()->create([
        'name' => 'Bob Partner',
        'mobile_phone' => '0422222222',
    ]);
    $solo = User::factory()->userRoleUser()->enabled()->create(['name' => 'Carol Solo']);
    $location = Location::factory()->create(['name' => 'Town Square']);
    $shift = Shift::factory()->everyDay9am()->for($location)->create();

    $recipient->attachShiftOnDate($shift, '2023-02-14');
    $partner->attachShiftOnDate($shift, '2023-02-14');
    $solo->attachShiftOnDate($shift, '2023-02-16');

    Mail::fake();
    $this->travelTo('2023-02-06 12:30:01');

    app(NotifyVolunteersOfReleasedShifts::class)->execute();

    Mail::assertSent(ShiftAssignmentsReleased::class, function (ShiftAssignmentsReleased $mail) use ($recipient) {
        return $mail->hasTo($recipient->email)
            && $mail->shifts->count() === 1
            && $mail->shifts->first()['other_volunteers'] === [
                ['name' => 'Bob Partner', 'mobile_phone' => '0422 222 222'],
            ];
    });
    Mail::assertSent(ShiftAssignmentsReleased::class, function (ShiftAssignmentsReleased $mail) use ($partner) {
        return $mail->hasTo($partner->email)
            && $mail->shifts->first()['other_volunteers'] === [
                ['name' => 'Alice Recipient', 'mobile_phone' => '0411 111 111'],
            ];
    });
    Mail::assertSent(ShiftAssignmentsReleased::class, function (ShiftAssignmentsReleased $mail) use ($solo) {
        return $mail->hasTo($solo->email)
            && $mail->shifts->first()['other_volunteers'] === [];
    });
});

test('email includes location notes and map url', function () {
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');

    $user = User::factory()->userRoleUser()->enabled()->create();
    $mapped = Location::factory()->create([
        'name' => 'Town Square',
        'description' => '<p>Meet at the <strong>north</strong> entrance.</p>',
        'latitude' => '-37.8136',
        'longitude' => '144.9631',
    ]);
    $unmapped = Location::factory()->create([
        'name' => 'Hall',
        'description' => '<p></p>',
        'latitude' => null,
        'longitude' => null,
    ]);
    $mappedShift = Shift::factory()->everyDay9am()->for($mapped)->create();
    $unmappedShift = Shift::factory()->everyDay9am()->for($unmapped)->create();

    $user->attachShiftOnDate($mappedShift, '2023-02-14');
    $user->attachShiftOnDate($unmappedShift, '2023-02-16');

    $mapped->refresh();

    Mail::fake();
    $this->travelTo('2023-02-06 12:30:01');

    app(NotifyVolunteersOfReleasedShifts::class)->execute();

    Mail::assertSent(ShiftAssignmentsReleased::class, function (ShiftAssignmentsReleased $mail) use ($user, $mapped) {
        $shifts = $mail->shifts->values();

        return $mail->hasTo($user->email)
            && $shifts[0]['location'] === 'Town Square'
            && $shifts[0]['location_description'] === 'Meet at the north entrance.'
            && $shifts[0]['location_map_url'] === 'https://www.google.com/maps?q='.$mapped->latitude.','.$mapped->longitude
            && $shifts[1]['location'] === 'Hall'
            && $shifts[1]['location_description'] === ''
            && $shifts[1]['location_map_url'] === null;
    });
});

test('force flag allows catch up outside release moment', function () {
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');

    $user = User::factory()->userRoleUser()->enabled()->create();
    $location = Location::factory()->create();
    $shift = Shift::factory()->everyDay9am()->for($location)->create();
    $user->attachShiftOnDate($shift, '2023-02-14');

    Mail::fake();

    $this->travelTo('2023-02-07 10:00:00');
    $this->assertSame(
        ['users_notified' => 0, 'assignments_notified' => 0],
        app(NotifyVolunteersOfReleasedShifts::class)->execute()
    );

    $result = app(NotifyVolunteersOfReleasedShifts::class)->execute(force: true);

    expect($result)->toBe(['users_notified' => 1, 'assignments_notified' => 1]);
    Mail::assertSent(ShiftAssignmentsReleased::class, 1);
});

test('skips disabled users and already tracked assignments', function () {
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');

    $enabledUser = User::factory()->userRoleUser()->enabled()->create();
    $disabledUser = User::factory()->userRoleUser()->create(['is_enabled' => false]);
    $location = Location::factory()->create();
    $shift = Shift::factory()->everyDay9am()->for($location)->create();

    $enabledUser->attachShiftOnDate($shift, '2023-02-14');
    $disabledUser->attachShiftOnDate($shift, '2023-02-15');

    $tracked = ShiftUser::query()->where('user_id', $enabledUser->id)->first();
    ShiftAssignmentNotification::create([
        'shift_user_id' => $tracked->id,
        'sent_at' => now(),
    ]);

    Mail::fake();
    $this->travelTo('2023-02-06 12:30:01');

    $this->assertSame(
        ['users_notified' => 0, 'assignments_notified' => 0],
        app(NotifyVolunteersOfReleasedShifts::class)->execute()
    );
    Mail::assertNothingSent();
});

test('artisan command outputs summary', function () {
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');
    Config::set('cart-scheduler.shift_assignment_notifications_enabled', true);

    $user = User::factory()->userRoleUser()->enabled()->create();
    $location = Location::factory()->create();
    $shift = Shift::factory()->everyDay9am()->for($location)->create();
    $user->attachShiftOnDate($shift, '2023-02-14');

    Mail::fake();
    $this->travelTo('2023-02-06 12:30:01');

    $this->artisan(NotifyReleasedShiftAssignmentsCommand::class)
        ->expectsOutput('Queued notifications for 1 volunteer(s) covering 1 assignment(s).')
        ->assertSuccessful();
});

test('artisan command respects disabled flag', function () {
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');
    Config::set('cart-scheduler.shift_assignment_notifications_enabled', false);

    $user = User::factory()->userRoleUser()->enabled()->create();
    $location = Location::factory()->create();
    $shift = Shift::factory()->everyDay9am()->for($location)->create();
    $user->attachShiftOnDate($shift, '2023-02-14');

    Mail::fake();
    $this->travelTo('2023-02-06 12:30:01');

    $this->artisan(NotifyReleasedShiftAssignmentsCommand::class)
        ->expectsOutput('Shift assignment notifications are disabled (CA_SHIFT_ASSIGNMENT_NOTIFICATIONS_ENABLED).')
        ->assertSuccessful();
    Mail::assertNothingSent();
});

test('execute queues one job per user without recording until the job runs', function () {
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');

    $user = User::factory()->userRoleUser()->enabled()->create();
    $location = Location::factory()->create();
    $shift = Shift::factory()->everyDay9am()->for($location)->create();
    $user->attachShiftOnDate($shift, '2023-02-14');

    Queue::fake();
    Mail::fake();
    $this->travelTo('2023-02-06 12:30:01');

    $result = app(NotifyVolunteersOfReleasedShifts::class)->execute();

    expect($result)->toBe(['users_notified' => 1, 'assignments_notified' => 1]);
    Queue::assertPushed(SendShiftAssignmentsReleasedMail::class, 1);
    Queue::assertPushed(SendShiftAssignmentsReleasedMail::class, function (SendShiftAssignmentsReleasedMail $job) use ($user) {
        return $job->user->is($user)
            && $job->shifts->count() === 1
            && $job->period === [
                'start' => '13 February 2023',
                'end' => '19 February 2023',
                'label' => '13 February 2023 – 19 February 2023',
            ];
    });
    Mail::assertNothingSent();
    $this->assertDatabaseCount('shift_assignment_notifications', 0);
});

test('does not queue a second job for the same assignments while the first is pending', function () {
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');

    $user = User::factory()->userRoleUser()->enabled()->create();
    $location = Location::factory()->create();
    $shift = Shift::factory()->everyDay9am()->for($location)->create();
    $user->attachShiftOnDate($shift, '2023-02-14');

    Queue::fake();
    $this->travelTo('2023-02-06 12:30:01');

    app(NotifyVolunteersOfReleasedShifts::class)->execute();
    app(NotifyVolunteersOfReleasedShifts::class)->execute();

    Queue::assertPushed(SendShiftAssignmentsReleasedMail::class, 1);
});
