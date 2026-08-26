<?php

use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Payloads a browser would act on if the description reached `v-html`
 * unsanitised. Each sets its own sentinel so a failure names the vector that
 * got through rather than just reporting that something ran.
 */
const XSS_PAYLOAD = '<p>North entry, near the fountain.</p>'
    .'<script>window.__xss_script = 1</script>'
    .'<img src=x onerror="window.__xss_img = 1">'
    .'<svg onload="window.__xss_svg = 1"></svg>'
    .'<p onclick="window.__xss_click = 1">Tap</p>'
    .'<a href="javascript:window.__xss_link = 1">Directions</a>'
    .'<iframe src="//evil.tld"></iframe>';

test('a stored xss payload in a location description does not execute', function () {
    $user = User::factory()->enabled()->male()->create();

    /** @var Location $location */
    $location = Location::factory()
        ->state(['max_volunteers' => 3, 'requires_brother' => false])
        ->has(Shift::factory()->everyDay9am())
        ->create();

    // Written straight to the table, deliberately bypassing CreateLocationRequest
    // and UpdateLocationRequest. Those run App\Actions\SanitiseRichText, so going
    // through them would test the write path and never exercise the renderer. A
    // row stored before sanitising-on-write existed looks exactly like this, and
    // resources/js/Utils/sanitiseRichText.ts is all that stands between it and
    // the volunteer's browser.
    DB::table('locations')->where('id', $location->id)->update(['description' => XSS_PAYLOAD]);

    $this->actingAs($user);

    $page = visit('/');

    // A fresh user is prompted to confirm their availability, and that dialog
    // sits over the dashboard swallowing clicks until it is dismissed.
    $page->click("I'll check later");

    $page->click($location->name);

    // The harmless half of the payload has to be on screen before the sentinel
    // assertions mean anything — otherwise they would pass just as happily on a
    // page that never rendered the description at all.
    $page->assertSee('North entry, near the fountain.');

    expect($page->script('window.__xss_script ?? null'))->toBeNull()
        ->and($page->script('window.__xss_img ?? null'))->toBeNull()
        ->and($page->script('window.__xss_svg ?? null'))->toBeNull()
        ->and($page->script('window.__xss_click ?? null'))->toBeNull()
        ->and($page->script('window.__xss_link ?? null'))->toBeNull();

    // The payload's markup should be gone from the document, not merely inert.
    // Matching on the payload's own strings rather than counting tags, because
    // the application legitimately renders its own scripts on every page.
    expect($page->script('document.body.innerHTML.includes("__xss_")'))->toBeFalse()
        ->and($page->script('document.body.innerHTML.includes("evil.tld")'))->toBeFalse();
});
