<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidEmailTemplatePlaceholders;
use App\Settings\GeneralSettings;
use Tests\TestCase;

class ValidEmailTemplatePlaceholdersTest extends TestCase
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
    }

    public function test_it_allows_nested_shift_loops(): void
    {
        $this->assertNull($this->failureFor(<<<'BODY'
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
    }

    public function test_it_rejects_shift_variables_outside_the_loop(): void
    {
        $this->assertSame(
            'The placeholder {{ shift.date }} can only be used inside {{#each shifts }}.',
            $this->failureFor('Date: {{ shift.date }}'),
        );
    }

    public function test_it_rejects_nested_loop_outside_parent(): void
    {
        $this->assertSame(
            'The loop {{#each shift.other_volunteers }} can only be used inside {{#each shifts }}.',
            $this->failureFor('{{#each shift.other_volunteers}}{{ volunteer.name }}{{/each}}'),
        );
    }

    public function test_it_rejects_unknown_loops(): void
    {
        $this->assertSame(
            'The loop {{#each unknown }} is not allowed for this template.',
            $this->failureFor('{{#each unknown}}{{/each}}'),
        );
    }

    public function test_it_rejects_unbalanced_each_tags(): void
    {
        $this->assertSame(
            'The {{#each}} and {{/each}} tags are not balanced.',
            $this->failureFor('{{#each shifts}}{{ shift.date }}'),
        );
    }

    public function test_it_rejects_empty_outside_a_loop(): void
    {
        $this->assertSame(
            'The {{#empty}} tag can only be used inside a {{#each}} loop.',
            $this->failureFor('{{#empty}}None{{/each}}'),
        );
    }

    private function failureFor(string $body): ?string
    {
        $failed = null;
        $rule = new ValidEmailTemplatePlaceholders('shift-assignments-released');
        $rule->validate('body', $body, static function (string $message) use (&$failed): void {
            $failed = $message;
        });

        return $failed;
    }
}
