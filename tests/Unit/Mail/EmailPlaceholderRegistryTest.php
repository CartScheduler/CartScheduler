<?php

use App\Mail\EmailPlaceholderRegistry;
use App\Settings\GeneralSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    GeneralSettings::fake([
        'siteName' => 'SMPW Melbourne',
        'allowedSettingsUsers' => [],
        'systemShiftStartHour' => 8,
        'systemShiftEndHour' => 16,
        'enableUserAvailability' => false,
        'enableUserLocationChoices' => false,
    ]);
});

test('placeholders are grouped into recipient app and email categories', function () {
    $placeholders = Collection::make(
        app(EmailPlaceholderRegistry::class)->placeholdersFor('shift-assignments-released'),
    );

    $this->assertSame(
        [
            'user.name',
            'user.first_name',
            'user.last_name',
            'user.email',
            'user.mobile_phone',
            'user.title',
            'user.prefixed_name',
            'user.gender',
            'user.appointment',
            'user.serving_as',
            'user.spouse_name',
        ],
        $placeholders->where('category', 'recipient')->pluck('key')->all(),
    );
    $this->assertSame(
        [
            'app.name',
            'app.site_name',
            'app.url',
            'mail.support.address',
            'mail.support.name',
            'mail.from.address',
            'mail.from.name',
        ],
        $placeholders->where('category', 'app')->pluck('key')->all(),
    );
    $this->assertSame(
        ['period.start', 'period.end', 'period.label', 'shifts.count', 'shifts', 'each.shifts', 'shift.date', 'shift.location', 'shift.location_map_url', 'shift.location_description', 'shift.start_time', 'shift.end_time', 'each.shift.other_volunteers', 'volunteer.name', 'volunteer.mobile_phone'],
        $placeholders->where('category', 'email')->pluck('key')->all(),
    );
});

test('user account created includes confirm account placeholders', function () {
    $placeholders = Collection::make(
        app(EmailPlaceholderRegistry::class)->placeholdersFor('user-account-created'),
    )->where('category', 'email');

    $this->assertSame(
        ['confirm_account_url', 'confirm_account_button'],
        $placeholders->pluck('key')->all(),
    );
});

test('app placeholders include configured values', function () {
    Config::set('app.name', 'Cart Scheduler');
    Config::set('app.url', 'https://cart.example.test');
    Config::set('mail.support.address', 'support@example.test');
    Config::set('mail.support.name', 'SMPW Admin Team');
    Config::set('mail.from.address', 'from@example.test');
    Config::set('mail.from.name', 'Cart Scheduler');

    $placeholders = Collection::make(
        app(EmailPlaceholderRegistry::class)->placeholdersFor('user-account-created'),
    )->where('category', 'app')->keyBy('key');

    $this->assertSame('Cart Scheduler', $placeholders['app.name']['value']);
    $this->assertSame('SMPW Melbourne', $placeholders['app.site_name']['value']);
    $this->assertSame('https://cart.example.test', $placeholders['app.url']['value']);
    $this->assertSame('support@example.test', $placeholders['mail.support.address']['value']);
    $this->assertSame('SMPW Admin Team', $placeholders['mail.support.name']['value']);
    $this->assertSame('from@example.test', $placeholders['mail.from.address']['value']);
    $this->assertSame('Cart Scheduler', $placeholders['mail.from.name']['value']);
    $this->assertNull(
        Collection::make(app(EmailPlaceholderRegistry::class)->placeholdersFor('user-account-created'))
            ->firstWhere('key', 'user.name')['value'] ?? null,
    );
});

test('period variables collapse a single day window', function () {
    $period = app(EmailPlaceholderRegistry::class)->periodVariables(
        Carbon::parse('2026-02-14 00:00:00'),
        Carbon::parse('2026-02-14 23:59:59'),
    );

    $this->assertSame(
        [
            'start' => '14 February 2026',
            'end' => '14 February 2026',
            'label' => '14 February 2026',
        ],
        $period,
    );
});

test('period variables join a date range', function () {
    $period = app(EmailPlaceholderRegistry::class)->periodVariables(
        Carbon::parse('2026-02-01'),
        Carbon::parse('2026-02-28'),
    );

    expect($period['label'])->toBe('1 February 2026 – 28 February 2026');
});

test('shift loop placeholders expose item aliases and nesting', function () {
    $registry = app(EmailPlaceholderRegistry::class);

    $this->assertSame(
        [
            'shifts' => 'shift',
            'shift.other_volunteers' => 'volunteer',
        ],
        $registry->loopItemAliases('shift-assignments-released'),
    );
    $this->assertSame('shifts', $registry->requiredLoopCollection('shift-assignments-released', 'shift.date'));
    $this->assertSame(
        'shift.other_volunteers',
        $registry->requiredLoopCollection('shift-assignments-released', 'volunteer.name'),
    );
    $this->assertSame(
        'shifts',
        $registry->requiredLoopCollection('shift-assignments-released', 'shift.other_volunteers'),
    );
    expect($registry->requiredLoopCollection('shift-assignments-released', 'shifts.count'))->toBeNull();
});
