<?php

use App\Mail\EmailTemplateRenderer;
use App\Models\User;
use App\Settings\GeneralSettings;
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
    Config::set('app.name', 'Cart Scheduler');
    Config::set('app.url', 'https://cart.example.test');
    Config::set('mail.support.address', 'support@example.test');
    Config::set('mail.support.name', 'SMPW Admin Team');
    Config::set('mail.from.address', 'from@example.test');
    Config::set('mail.from.name', 'Cart Scheduler');
});

test('preview replaces recipient variables with sample data', function () {
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

    expect($preview['subject'])->toBe('Hello Jane');
    expect($preview['html'])->toContain('Jane Volunteer');
    expect($preview['html'])->toContain('jane@example.com');
    expect($preview['html'])->toContain('0400 000 000');
    expect($preview['html'])->toContain('Sis Jane Volunteer');
    expect($preview['html'])->toContain('Female');
    expect($preview['html'])->toContain('Publisher');
    expect($preview['html'])->toContain('John Volunteer');
});

test('preview replaces app mail and confirm account url', function () {
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

    expect($preview['subject'])->toBe('SMPW Melbourne for Cart Scheduler');
    expect($preview['html'])->toContain('https://cart.example.test');
    expect($preview['html'])->toContain('support@example.test');
    expect($preview['html'])->toContain('SMPW Admin Team');
    expect($preview['html'])->toContain('from@example.test');
    $this->assertStringContainsString(
        'https://cart.example.test/set-password/1/sample-preview-token',
        $preview['html'],
    );
});

test('preview replaces period and shift count', function () {
    $preview = app(EmailTemplateRenderer::class)->preview(
        'shift-assignments-released',
        '{{ app.site_name }} Shift Assignments: {{ period.label }}',
        '{{ period.start }} to {{ period.end }} ({{ shifts.count }})',
    );

    expect($preview['subject'])->toBe('SMPW Melbourne Shift Assignments: 1 February 2026 – 28 February 2026');
    expect($preview['html'])->toContain('1 February 2026 to 28 February 2026 (2)');
});

test('preview renders confirm account button', function () {
    $preview = app(EmailTemplateRenderer::class)->preview(
        'user-account-created',
        'Activate',
        '{{ confirm_account_button }}',
    );

    $this->assertStringContainsString(
        'https://cart.example.test/set-password/1/sample-preview-token',
        $preview['html'],
    );
    expect($preview['html'])->toContain('Confirm Account');
});

test('preview includes location notes and map link', function () {
    $preview = app(EmailTemplateRenderer::class)->preview(
        'shift-assignments-released',
        'Shifts',
        '{{ shifts }}',
    );

    expect($preview['html'])->toContain('Meet at the north entrance near the cart.');
    expect($preview['html'])->toContain('https://www.google.com/maps?q=-37.8136,144.9631');
    expect($preview['html'])->toContain('Town Square');
    expect($preview['html'])->toContain('City Centre');
});

test('preview renders shift loops and nested volunteers', function () {
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

    expect($preview['html'])->toContain('Monday, 14 February 2026');
    expect($preview['html'])->toContain('Town Square');
    $this->assertMatchesRegularExpression(
        '/Monday, 14 February 2026.*?<br\s*\/?>.*?Town Square/s',
        $preview['html'],
    );
    expect($preview['html'])->toContain('https://www.google.com/maps?q=-37.8136,144.9631');
    expect($preview['html'])->toContain('Meet at the north entrance near the cart.');
    expect($preview['html'])->toContain('John Smith');
    expect($preview['html'])->toContain('0400 000 000');
    $this->assertDoesNotMatchRegularExpression(
        '/John Smith\s*<br\s*\/?>\s*—\s*0400 000 000/s',
        $preview['html'],
    );
    expect($preview['html'])->toContain('Wednesday, 16 February 2026');
    expect($preview['html'])->toContain('City Centre');
    expect($preview['html'])->not->toContain('None');
});

test('preview renders empty volunteer fallback', function () {
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

    expect($rendered)->toBe("\nNone\n");
});

test('recipient variables are derived from a user model', function () {
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
    expect($context['user']->id)->toBe(9);
});

test('placeholder values are html escaped in the body', function () {
    $renderer = app(EmailTemplateRenderer::class);

    $rendered = $renderer->replacePlaceholders(
        'Dear {{ user.name }},',
        'user-account-created',
        ['user' => ['name' => '<a href="https://evil.test">Confirm</a><img src="https://evil.test/p.png">']],
    );

    expect($rendered)
        ->not->toContain('<a href')
        ->not->toContain('<img')
        ->toContain('&lt;a href=&quot;https://evil.test&quot;&gt;Confirm&lt;/a&gt;');
});

test('placeholder values inside loops are html escaped', function () {
    $rendered = app(EmailTemplateRenderer::class)->replacePlaceholders(
        '{{#each shifts}}{{#each shift.other_volunteers}}{{ volunteer.name }}{{/each}}{{/each}}',
        'shift-assignments-released',
        ['shifts' => [['other_volunteers' => [['name' => 'Bob <script>alert(1)</script>']]]]],
    );

    expect($rendered)->toBe('Bob &lt;script&gt;alert(1)&lt;/script&gt;');
});

test('placeholder values cannot inject another placeholder', function () {
    $renderer = app(EmailTemplateRenderer::class);
    $context = [
        'user' => ['name' => '{{ mail.from.address }}'],
        'mail' => ['from' => ['address' => 'from@example.test']],
    ];

    $body = $renderer->replacePlaceholders('Hi {{ user.name }}', 'user-account-created', $context);
    $subject = $renderer->replacePlaceholders('Hi {{ user.name }}', 'user-account-created', $context, escapeHtml: false);

    expect($body)->not->toContain('from@example.test')
        ->and($subject)->not->toContain('from@example.test');
});

test('subject values are not html escaped', function () {
    $subject = app(EmailTemplateRenderer::class)->replacePlaceholders(
        'Welcome {{ user.name }}',
        'user-account-created',
        ['user' => ['name' => "Tom & Mary O'Brien"]],
        escapeHtml: false,
    );

    expect($subject)->toBe("Welcome Tom & Mary O'Brien");
});
