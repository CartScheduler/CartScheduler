<?php

namespace Tests\Unit\Mail;

use App\Enums\DBPeriod;
use App\Enums\EmailTemplateTriggerType;
use App\Mail\EmailTemplateTriggerResolver;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Tests\Traits\SetConfig;

class EmailTemplateTriggerResolverTest extends TestCase
{
    use SetConfig;

    public function test_shift_assignments_weekly_schedule_explains_when_the_email_is_sent(): void
    {
        Config::set('app.timezone', 'UTC');
        Config::set('cart-scheduler.shift_assignment_notifications_enabled', true);
        $this->setConfig(2, DBPeriod::Week, false, 'MON', '12:00');

        $trigger = app(EmailTemplateTriggerResolver::class)->for('shift-assignments-released');

        $this->assertNotNull($trigger);
        $this->assertSame(EmailTemplateTriggerType::System, $trigger->type);
        $this->assertTrue($trigger->enabled);
        $this->assertStringContainsString('every Monday at 12:00 PM (UTC)', $trigger->summary);
        $this->assertStringContainsString('2 weeks ahead', $trigger->summary);
    }

    public function test_shift_assignments_disabled_note_and_monthly_schedule(): void
    {
        Config::set('app.timezone', 'Australia/Melbourne');
        Config::set('cart-scheduler.shift_assignment_notifications_enabled', false);
        $this->setConfig(1, DBPeriod::Month, false, 'SUN', '09:00');

        $trigger = app(EmailTemplateTriggerResolver::class)->for('shift-assignments-released');

        $this->assertNotNull($trigger);
        $this->assertSame(EmailTemplateTriggerType::System, $trigger->type);
        $this->assertFalse($trigger->enabled);
        $this->assertStringContainsString('on the 1st of each month at 9:00 AM (Australia/Melbourne)', $trigger->summary);
        $this->assertStringContainsString('1 month ahead', $trigger->summary);
    }

    public function test_shift_assignments_daily_schedule(): void
    {
        Config::set('app.timezone', 'UTC');
        Config::set('cart-scheduler.shift_assignment_notifications_enabled', true);
        $this->setConfig(1, DBPeriod::Week, true, 'MON', '00:00:00');

        $trigger = app(EmailTemplateTriggerResolver::class)->for('shift-assignments-released');

        $this->assertNotNull($trigger);
        $this->assertSame(EmailTemplateTriggerType::System, $trigger->type);
        $this->assertTrue($trigger->enabled);
        $this->assertStringContainsString('each day at 12:00 AM (UTC)', $trigger->summary);
    }

    public function test_user_account_created_trigger_explains_when_the_email_is_sent(): void
    {
        $trigger = app(EmailTemplateTriggerResolver::class)->for('user-account-created');

        $this->assertNotNull($trigger);
        $this->assertSame(EmailTemplateTriggerType::System, $trigger->type);
        $this->assertTrue($trigger->enabled);
        $this->assertStringContainsString('creates a new user account', $trigger->summary);
    }

    public function test_unknown_template_has_no_trigger(): void
    {
        $this->assertNull(app(EmailTemplateTriggerResolver::class)->for('unknown-template'));
    }
}
