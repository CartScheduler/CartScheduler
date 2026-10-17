<?php

use App\Rules\ValidEmailTemplatePlaceholders;
use App\Settings\GeneralSettings;

beforeEach(function () {
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
});

test('it allows nested shift loops', function () {
    $this->assertNull(placeholderRuleFailureFor(<<<'BODY'
{{#each shifts}}
{{ shift.date }} {{ shift.location }}
{{#if shift.location_map_url}}{{ shift.location_map_url }}{{/if}}
{{#each shift.other_volunteers}}
{{ volunteer.name }}{{#if volunteer.mobile_phone}} {{ volunteer.mobile_phone }}{{/if}}
{{#empty}}
None
{{/each}}
{{/each}}
BODY));
});

test('it rejects shift variables outside the loop', function () {
    $this->assertSame(
        'The placeholder {{ shift.date }} can only be used inside {{#each shifts }}.',
        placeholderRuleFailureFor('Date: {{ shift.date }}'),
    );
});

test('it rejects nested loop outside parent', function () {
    $this->assertSame(
        'The loop {{#each shift.other_volunteers }} can only be used inside {{#each shifts }}.',
        placeholderRuleFailureFor('{{#each shift.other_volunteers}}{{ volunteer.name }}{{/each}}'),
    );
});

test('it rejects unknown loops', function () {
    $this->assertSame(
        'The loop {{#each unknown }} is not allowed for this template.',
        placeholderRuleFailureFor('{{#each unknown}}{{/each}}'),
    );
});

test('it rejects unbalanced each tags', function () {
    $this->assertSame(
        'The {{#each}} and {{/each}} tags are not balanced.',
        placeholderRuleFailureFor('{{#each shifts}}{{ shift.date }}'),
    );
});

test('it rejects empty outside a loop', function () {
    $this->assertSame(
        'The {{#empty}} tag can only be used inside a {{#each}} loop.',
        placeholderRuleFailureFor('{{#empty}}None{{/each}}'),
    );
});

function placeholderRuleFailureFor(string $body): ?string
{
    $failed = null;
    $rule = new ValidEmailTemplatePlaceholders('shift-assignments-released');
    $rule->validate('body', $body, static function (string $message) use (&$failed): void {
        $failed = $message;
    });

    return $failed;
}
