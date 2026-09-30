<?php

namespace Tests\Unit\Mail;

use App\Mail\EmailTemplateRenderer;
use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Config;
use ReflectionMethod;
use Tests\TestCase;

class EmailTemplateRendererTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        GeneralSettings::fake([
            'siteName' => 'SMPW Melbourne',
            'currentVersion' => 'v0.0.0',
            'availableVersion' => 'v0.0.0',
            'allowedSettingsUsers' => [],
            'systemShiftStartHour' => 8,
            'systemShiftEndHour' => 16,
            'enableUserAvailability' => false,
            'enableUserLocationChoices' => false,
        ]);
        Config::set('app.name', 'Cart Scheduler');
        Config::set('app.url', 'https://cart.example.test');
        Config::set('mail.support.address', 'support@example.test');
        Config::set('mail.support.name', 'SMPW Admin Team');
        Config::set('mail.from.address', 'from@example.test');
        Config::set('mail.from.name', 'Cart Scheduler');
    }

    public function test_preview_replaces_recipient_variables_with_sample_data(): void
    {
        $preview = app(EmailTemplateRenderer::class)->preview(
            'user-account-created',
            'Hello {{ user.first_name }}',
            implode("\n", [
                '{{ user.name }}',
                '{{ user.first_name }}',
                '{{ user.last_name }}',
                '{{ user.email }}',
                '{{ user.mobile_phone }}',
                '{{ user.title }}',
                '{{ user.prefixed_name }}',
                '{{ user.gender }}',
                '{{ user.appointment }}',
                '{{ user.serving_as }}',
                '{{ user.spouse_name }}',
            ]),
        );

        $this->assertSame('Hello Jane', $preview['subject']);
        $this->assertStringContainsString('Jane Volunteer', $preview['html']);
        $this->assertStringContainsString('jane@example.com', $preview['html']);
        $this->assertStringContainsString('0400 000 000', $preview['html']);
        $this->assertStringContainsString('Sis Jane Volunteer', $preview['html']);
        $this->assertStringContainsString('Female', $preview['html']);
        $this->assertStringContainsString('Publisher', $preview['html']);
        $this->assertStringContainsString('John Volunteer', $preview['html']);
    }

    public function test_preview_replaces_app_mail_and_confirm_account_url(): void
    {
        $preview = app(EmailTemplateRenderer::class)->preview(
            'user-account-created',
            '{{ app.site_name }} for {{ app.name }}',
            implode("\n", [
                '{{ app.url }}',
                '{{ mail.support.address }}',
                '{{ mail.support.name }}',
                '{{ mail.from.address }}',
                '{{ mail.from.name }}',
                '{{ confirm_account_url }}',
            ]),
        );

        $this->assertSame('SMPW Melbourne for Cart Scheduler', $preview['subject']);
        $this->assertStringContainsString('https://cart.example.test', $preview['html']);
        $this->assertStringContainsString('support@example.test', $preview['html']);
        $this->assertStringContainsString('SMPW Admin Team', $preview['html']);
        $this->assertStringContainsString('from@example.test', $preview['html']);
        $this->assertStringContainsString(
            'https://cart.example.test/set-password/1/sample-preview-token',
            $preview['html'],
        );
    }

    public function test_preview_replaces_period_and_shift_count(): void
    {
        $preview = app(EmailTemplateRenderer::class)->preview(
            'shift-assignments-released',
            '{{ app.site_name }} Shift Assignments: {{ period.label }}',
            '{{ period.start }} to {{ period.end }} ({{ shifts.count }})',
        );

        $this->assertSame('SMPW Melbourne Shift Assignments: 1 February 2026 – 28 February 2026', $preview['subject']);
        $this->assertStringContainsString('1 February 2026 to 28 February 2026 (2)', $preview['html']);
    }

    public function test_preview_renders_confirm_account_button(): void
    {
        $preview = app(EmailTemplateRenderer::class)->preview(
            'user-account-created',
            'Activate',
            '{{ confirm_account_button }}',
        );

        $this->assertStringContainsString(
            'https://cart.example.test/set-password/1/sample-preview-token',
            $preview['html'],
        );
        $this->assertStringContainsString('Confirm Account', $preview['html']);
    }

    public function test_preview_includes_location_notes_and_map_link(): void
    {
        $preview = app(EmailTemplateRenderer::class)->preview(
            'shift-assignments-released',
            'Shifts',
            '{{ shifts }}',
        );

        $this->assertStringContainsString('Meet at the north entrance near the cart.', $preview['html']);
        $this->assertStringContainsString('https://www.google.com/maps?q=-37.8136,144.9631', $preview['html']);
        $this->assertStringContainsString('Town Square', $preview['html']);
        $this->assertStringContainsString('City Centre', $preview['html']);
    }

    public function test_preview_renders_shift_loops_and_nested_volunteers(): void
    {
        $preview = app(EmailTemplateRenderer::class)->preview(
            'shift-assignments-released',
            'Shifts',
            implode("\n", [
                '{{#each shifts}}',
                '**Date:** {{ shift.date }}<br>',
                '**Location:** {{ shift.location }}{{#if shift.location_map_url}} ([Map]({{ shift.location_map_url }})){{/if}}<br>',
                '{{#if shift.location_description}}',
                '{{ shift.location_description }}<br>',
                '{{/if}}',
                '**Start Time:** {{ shift.start_time }}<br>',
                '**Finish Time:** {{ shift.end_time }}<br>',
                '**Other Volunteers:**',
                '',
                '<ul>',
                '{{#each shift.other_volunteers}}',
                '<li>{{ volunteer.name }}{{#if volunteer.mobile_phone}} — {{ volunteer.mobile_phone }}{{/if}}</li>',
                '{{#empty}}',
                '<li>None</li>',
                '{{/each}}',
                '</ul>',
                '',
                '{{/each}}',
            ]),
        );

        $this->assertStringContainsString('Monday, 14 February 2026', $preview['html']);
        $this->assertStringContainsString('Town Square', $preview['html']);
        $this->assertMatchesRegularExpression(
            '/Monday, 14 February 2026.*?<br\s*\/?>.*?Town Square/s',
            $preview['html'],
        );
        $this->assertStringContainsString('https://www.google.com/maps?q=-37.8136,144.9631', $preview['html']);
        $this->assertStringContainsString('Meet at the north entrance near the cart.', $preview['html']);
        $this->assertStringContainsString('John Smith', $preview['html']);
        $this->assertStringContainsString('0400 000 000', $preview['html']);
        $this->assertDoesNotMatchRegularExpression(
            '/John Smith\s*<br\s*\/?>\s*—\s*0400 000 000/s',
            $preview['html'],
        );
        $this->assertStringContainsString('Wednesday, 16 February 2026', $preview['html']);
        $this->assertStringContainsString('City Centre', $preview['html']);
        $this->assertStringNotContainsString('None', $preview['html']);
    }

    public function test_preview_renders_empty_volunteer_fallback(): void
    {
        $renderer = app(EmailTemplateRenderer::class);
        $buildRenderContext = new ReflectionMethod($renderer, 'buildRenderContext');
        $context = $buildRenderContext->invoke($renderer, [
            'user' => (object) ['id' => 1, 'name' => 'Jane Volunteer', 'email' => 'jane@example.com'],
            'shifts' => [
                [
                    'date' => 'Monday, 14 February 2026',
                    'start_time' => '9:00 AM',
                    'end_time' => '11:00 AM',
                    'location' => 'Town Square',
                    'location_description' => '',
                    'location_map_url' => null,
                    'other_volunteers' => [],
                ],
            ],
        ]);

        $rendered = $renderer->replacePlaceholders(
            "{{#each shifts}}\n{{#each shift.other_volunteers}}{{ volunteer.name }}{{#empty}}None{{/each}}\n{{/each}}",
            'shift-assignments-released',
            $context,
        );

        $this->assertSame("\nNone\n", $rendered);
    }

    public function test_recipient_variables_are_derived_from_a_user_model(): void
    {
        $user = new User([
            'name' => 'John Elder',
            'email' => 'john@example.com',
            'gender' => 'male',
            'mobile_phone' => '0411222333',
            'appointment' => 'elder',
            'serving_as' => 'regular pioneer',
        ]);
        $user->id = 9;
        $user->setRelation('spouse', new User(['name' => 'Mary Elder']));

        $renderer = app(EmailTemplateRenderer::class);
        $buildRenderContext = new ReflectionMethod($renderer, 'buildRenderContext');
        $context = $buildRenderContext->invoke($renderer, ['user' => $user, 'token' => 'abc123']);

        $rendered = $renderer->replacePlaceholders(
            '{{ user.first_name }}|{{ user.last_name }}|{{ user.title }}|{{ user.prefixed_name }}|{{ user.appointment }}|{{ user.serving_as }}|{{ user.mobile_phone }}|{{ user.spouse_name }}|{{ confirm_account_url }}',
            'user-account-created',
            $context,
        );

        $this->assertSame(
            'John|Elder|Bro|Bro John Elder|Elder|Regular Pioneer|0411 222 333|Mary Elder|https://cart.example.test/set-password/9/abc123',
            $rendered,
        );
        $this->assertSame(9, $context['user']->id);
    }
}
