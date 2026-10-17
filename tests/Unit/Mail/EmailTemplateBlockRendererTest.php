<?php

use App\Mail\EmailTemplateBlockRenderer;

test('it repeats content for each item', function () {
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

    expect($rendered)->toBe('Monday Wednesday ');
});

test('it renders nested loops and empty fallback', function () {
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

    expect($rendered)->toBe('John,Mary,;None;');
});

test('it hides falsey if blocks', function () {
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

    expect($rendered)->toBe('https://maps.example/one');
});

test('it leaves plain content unchanged', function () {
    $rendered = app(EmailTemplateBlockRenderer::class)->render(
        'Hello {{ user.name }}',
        ['user' => ['name' => 'Jane']],
    );

    expect($rendered)->toBe('Hello {{ user.name }}');
});
