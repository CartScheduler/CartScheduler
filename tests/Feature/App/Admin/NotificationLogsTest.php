<?php

use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Models\NotificationLog;
use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->admin = User::factory()->enabled()->adminRoleUser()->create();
    $settings = app(GeneralSettings::class);
    $settings->allowedSettingsUsers = [$this->admin->getKey()];
    $settings->save();
});

test('an allowed admin sees the notification log with stats and filter options', function () {
    $volunteer = User::factory()->enabled()->create(['name' => 'Vera Volunteer']);
    NotificationLog::factory()->forUser($volunteer)->create(['sent_at' => now()->subMinutes(5)]);
    NotificationLog::factory()->failed()->create();

    $this->actingAs($this->admin)
        ->get('/admin/notifications')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Notifications/List')
            ->has('logs.data', 2)
            ->where('stats.sent_last_10_minutes', 1)
            ->where('stats.failed_last_7_days', 1)
            ->where('filters.status', null)
            ->has('options.statuses', count(NotificationStatus::cases()))
            ->has('options.periods', 5)
            ->where('options.types', fn ($types) => collect($types)->pluck('value')->contains(NotificationType::ShiftAssignmentsReleased->value))
            ->where('logs.data', fn ($logs) => collect($logs)->contains(fn ($log) => $log['recipient_name'] === 'Vera Volunteer'
                && $log['type_label'] === 'User Account Created')));
});

test('admins not on the settings list and other users cannot see the log', function () {
    $otherAdmin = User::factory()->enabled()->adminRoleUser()->create();
    $user = User::factory()->enabled()->create();

    $this->actingAs($otherAdmin)->get('/admin/notifications')->assertNotFound();
    $this->actingAs($user)->get('/admin/notifications')->assertForbidden();
});

test('the failed filter includes stalled sends and explains errors in plain english', function () {
    NotificationLog::factory()->create();
    NotificationLog::factory()->failed()->create();
    NotificationLog::factory()->stalled()->create();

    $this->actingAs($this->admin)
        ->get('/admin/notifications?status=failed')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('logs.data', 2)
            ->where('filters.status', 'failed')
            ->where('logs.data', fn ($logs) => collect($logs)->every(fn ($log) => $log['status'] === 'failed')
                && collect($logs)->contains(fn ($log) => $log['status_label'] === 'Stalled')
                && collect($logs)->contains(fn ($log) => $log['error_summary'] === "Couldn't connect to the mail server. Check MAIL_HOST and MAIL_PORT, or the mail server may be down.")));
});

test('the log can be filtered by time period', function (string $period, int $expected) {
    NotificationLog::factory()->create(['updated_at' => now()->subMinutes(5)]);
    NotificationLog::factory()->create(['updated_at' => now()->subMinutes(30)]);
    NotificationLog::factory()->create(['updated_at' => now()->subHours(5)]);
    NotificationLog::factory()->create(['updated_at' => now()->subDays(3)]);

    $this->actingAs($this->admin)
        ->get("/admin/notifications?period={$period}")
        ->assertInertia(fn (AssertableInertia $page) => $page->has('logs.data', $expected));
})->with([
    'last 10 minutes' => ['10m', 1],
    'last hour' => ['1h', 2],
    'last 24 hours' => ['24h', 3],
    'last 7 days' => ['7d', 4],
]);

test('the log can be filtered by type, channel, date range and search', function () {
    $volunteer = User::factory()->enabled()->create(['name' => 'Searchable Person']);
    NotificationLog::factory()->forUser($volunteer)->ofType(NotificationType::ShiftAssignmentsReleased)->create();
    NotificationLog::factory()->create(['recipient' => 'someone@example.test', 'channel' => 'push']);
    NotificationLog::factory()->create(['subject' => 'Special subject line', 'updated_at' => now()->subDays(10)]);

    $count = fn (string $query) => $this->actingAs($this->admin)->get("/admin/notifications?{$query}")
        ->assertOk()
        ->viewData('page')['props']['logs']['total'];

    expect($count('type=shift-assignments-released'))->toBe(1)
        ->and($count('channel=push'))->toBe(1)
        ->and($count('search=Searchable'))->toBe(1)
        ->and($count('search=someone@example'))->toBe(1)
        ->and($count('search=Special+subject'))->toBe(1)
        ->and($count('from='.now()->subDays(11)->toDateString().'&to='.now()->subDays(9)->toDateString()))->toBe(1);
});

test('invalid filters are rejected', function () {
    $this->actingAs($this->admin)
        ->get('/admin/notifications?period=5y&status=lost')
        ->assertSessionHasErrors(['period', 'status']);
});

test('the log is paginated', function () {
    NotificationLog::factory()->count(30)->create();

    $this->actingAs($this->admin)
        ->get('/admin/notifications?page=2')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('logs.data', 5)
            ->where('logs.total', 30));
});
