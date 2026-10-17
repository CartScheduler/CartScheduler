<?php

use App\Enums\DBPeriod;
use App\Enums\EmailTemplateTriggerType;
use App\Mail\EmailTemplateTriggerResolver;
use Illuminate\Support\Facades\Config;
use Tests\Traits\SetConfig;

uses(SetConfig::class);

test('shift assignments weekly schedule explains when the email is sent', function () {
    Config::set('app.timezone', 'UTC');
    Config::set('cart-scheduler.shift_assignment_notifications_enabled', true);
    $this->setConfig(2, DBPeriod::Week, false, 'MON', '12:00');

    $trigger = app(EmailTemplateTriggerResolver::class)->for('shift-assignments-released');

    expect($trigger)->not->toBeNull();
    expect($trigger->type)->toBe(EmailTemplateTriggerType::System);
    expect($trigger->enabled)->toBeTrue();
    expect($trigger->summary)->toContain('every Monday at 12:00 PM (UTC)');
    expect($trigger->summary)->toContain('2 weeks ahead');
});

test('shift assignments disabled note and monthly schedule', function () {
    Config::set('app.timezone', 'Australia/Melbourne');
    Config::set('cart-scheduler.shift_assignment_notifications_enabled', false);
    $this->setConfig(1, DBPeriod::Month, false, 'SUN', '09:00');

    $trigger = app(EmailTemplateTriggerResolver::class)->for('shift-assignments-released');

    expect($trigger)->not->toBeNull();
    expect($trigger->type)->toBe(EmailTemplateTriggerType::System);
    expect($trigger->enabled)->toBeFalse();
    expect($trigger->summary)->toContain('on the 1st of each month at 9:00 AM (Australia/Melbourne)');
    expect($trigger->summary)->toContain('1 month ahead');
});

test('shift assignments daily schedule', function () {
    Config::set('app.timezone', 'UTC');
    Config::set('cart-scheduler.shift_assignment_notifications_enabled', true);
    $this->setConfig(1, DBPeriod::Week, true, 'MON', '00:00:00');

    $trigger = app(EmailTemplateTriggerResolver::class)->for('shift-assignments-released');

    expect($trigger)->not->toBeNull();
    expect($trigger->type)->toBe(EmailTemplateTriggerType::System);
    expect($trigger->enabled)->toBeTrue();
    expect($trigger->summary)->toContain('each day at 12:00 AM (UTC)');
});

test('user account created trigger explains when the email is sent', function () {
    $trigger = app(EmailTemplateTriggerResolver::class)->for('user-account-created');

    expect($trigger)->not->toBeNull();
    expect($trigger->type)->toBe(EmailTemplateTriggerType::System);
    expect($trigger->enabled)->toBeTrue();
    expect($trigger->summary)->toContain('creates a new user account');
});

test('unknown template has no trigger', function () {
    expect(app(EmailTemplateTriggerResolver::class)->for('unknown-template'))->toBeNull();
});
