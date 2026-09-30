<?php

namespace Tests\Unit\Mail;

use App\Mail\EmailTemplateRecipientResolver;
use Tests\TestCase;

class EmailTemplateRecipientResolverTest extends TestCase
{
    public function test_shift_assignments_explains_who_receives_the_email(): void
    {
        $recipients = app(EmailTemplateRecipientResolver::class)->for('shift-assignments-released');

        $this->assertNotNull($recipients);
        $this->assertStringContainsString('pre-assigned', $recipients->summary);
        $this->assertStringContainsString('reservation window', $recipients->summary);
    }

    public function test_user_account_created_explains_who_receives_the_email(): void
    {
        $recipients = app(EmailTemplateRecipientResolver::class)->for('user-account-created');

        $this->assertNotNull($recipients);
        $this->assertStringContainsString('account was created', $recipients->summary);
        $this->assertStringContainsString('welcome email is being resent', $recipients->summary);
    }

    public function test_unknown_template_has_no_recipients(): void
    {
        $this->assertNull(app(EmailTemplateRecipientResolver::class)->for('unknown-template'));
    }
}
