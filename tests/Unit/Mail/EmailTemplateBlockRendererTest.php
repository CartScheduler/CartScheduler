<?php

namespace Tests\Unit\Mail;

use App\Mail\EmailTemplateBlockRenderer;
use Tests\TestCase;

class EmailTemplateBlockRendererTest extends TestCase
{
    public function test_it_repeats_content_for_each_item(): void
    {
        $rendered = app(EmailTemplateBlockRenderer::class)->render(
            '{{#each shifts}}{{ shift.date }} {{/each}}',
            [
                'shifts' => [
                    ['date' => 'Monday'],
                    ['date' => 'Wednesday'],
                ],
            ],
            ['shifts' => 'shift'],
        );

        $this->assertSame('Monday Wednesday ', $rendered);
    }

    public function test_it_renders_nested_loops_and_empty_fallback(): void
    {
        $template = '{{#each shifts}}{{#each shift.other_volunteers}}{{ volunteer.name }},{{#empty}}None{{/each}};{{/each}}';

        $rendered = app(EmailTemplateBlockRenderer::class)->render(
            $template,
            [
                'shifts' => [
                    ['other_volunteers' => [['name' => 'John'], ['name' => 'Mary']]],
                    ['other_volunteers' => []],
                ],
            ],
            [
                'shifts' => 'shift',
                'shift.other_volunteers' => 'volunteer',
            ],
        );

        $this->assertSame('John,Mary,;None;', $rendered);
    }

    public function test_it_hides_falsey_if_blocks(): void
    {
        $rendered = app(EmailTemplateBlockRenderer::class)->render(
            '{{#each shifts}}{{#if shift.location_map_url}}{{ shift.location_map_url }}{{/if}}{{/each}}',
            [
                'shifts' => [
                    ['location_map_url' => 'https://maps.example/one'],
                    ['location_map_url' => null],
                    ['location_map_url' => ''],
                ],
            ],
            ['shifts' => 'shift'],
        );

        $this->assertSame('https://maps.example/one', $rendered);
    }

    public function test_it_leaves_plain_content_unchanged(): void
    {
        $rendered = app(EmailTemplateBlockRenderer::class)->render(
            'Hello {{ user.name }}',
            ['user' => ['name' => 'Jane']],
        );

        $this->assertSame('Hello {{ user.name }}', $rendered);
    }
}
