<?php

namespace Tests\Feature\App\Admin;

use App\Mail\EmailTemplateRenderer;
use App\Mail\ShiftAssignmentsReleased;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Settings\GeneralSettings;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class EmailTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private function allowSettingsUser(User $admin): void
    {
        $generalSettings = $this->app->make(GeneralSettings::class);
        $generalSettings->allowedSettingsUsers = [$admin->getKey()];
        $generalSettings->save();
    }

    public function test_allowed_admin_can_view_email_templates_index(): void
    {
        $this->withoutVite();

        $admin = User::factory()->adminRoleUser()->create();
        $this->allowSettingsUser($admin);

        $this->seed(EmailTemplateSeeder::class);

        $this->actingAs($admin)
            ->get('/admin/emails')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Emails/List')
                ->has('templates', 2)
                ->has('templates.0', fn (AssertableInertia $template) => $template
                    ->has('id')
                    ->has('key')
                    ->has('name')
                    ->has('subject')
                    ->has('description')
                    ->has('is_system')
                    ->has('updated_at')
                    ->has('trigger')
                    ->has('recipients')));
    }

    public function test_non_allowed_admin_cannot_access_email_routes(): void
    {
        $admin = User::factory()->adminRoleUser()->create();
        $other = User::factory()->adminRoleUser()->create();

        $generalSettings = $this->app->make(GeneralSettings::class);
        $generalSettings->allowedSettingsUsers = [$other->getKey()];
        $generalSettings->save();

        $this->seed(EmailTemplateSeeder::class);
        $template = EmailTemplate::query()->firstOrFail();

        $this->actingAs($admin)->get('/admin/emails')->assertNotFound();
        $this->actingAs($admin)->get("/admin/emails/{$template->id}/edit")->assertNotFound();
        $this->actingAs($admin)->put("/admin/emails/{$template->id}", [
            'subject' => '{{ app.name }} Test',
            'body' => 'Hello {{ user.name }}',
        ])->assertNotFound();
    }

    public function test_email_templates_cannot_be_created_or_deleted(): void
    {
        $admin = User::factory()->adminRoleUser()->create();
        $this->allowSettingsUser($admin);

        $this->seed(EmailTemplateSeeder::class);
        $template = EmailTemplate::findByKey('shift-assignments-released');

        $this->actingAs($admin)->get('/admin/emails/create')->assertNotFound();
        $this->actingAs($admin)->post('/admin/emails', [
            'name' => 'Custom Welcome',
            'subject' => '{{ app.name }} Welcome',
            'body' => 'Hello {{ user.name }}',
        ])->assertMethodNotAllowed();
        $this->actingAs($admin)->delete("/admin/emails/{$template->id}")->assertMethodNotAllowed();

        $this->assertDatabaseHas('email_templates', ['id' => $template->id]);
        $this->assertDatabaseMissing('email_templates', ['key' => 'custom-welcome']);
    }

    public function test_edit_page_includes_template_placeholders(): void
    {
        $this->withoutVite();

        $admin = User::factory()->adminRoleUser()->create();
        $this->allowSettingsUser($admin);

        $this->seed(EmailTemplateSeeder::class);
        $template = EmailTemplate::findByKey('shift-assignments-released');

        $this->actingAs($admin)
            ->get("/admin/emails/{$template->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Emails/Edit')
                ->where('template.key', 'shift-assignments-released')
                ->has('template.placeholders')
                ->has('template.placeholders.0.category')
                ->where('template.placeholders', function ($placeholders) {
                    $siteName = collect($placeholders)->firstWhere('key', 'app.site_name');
                    $periodLabel = collect($placeholders)->firstWhere('key', 'period.label');

                    return ($siteName['value'] ?? null) === app(GeneralSettings::class)->siteName
                        && ($periodLabel['key'] ?? null) === 'period.label';
                })
                ->has('template.trigger')
                ->has('template.recipients'));
    }

    public function test_shift_assignments_edit_page_explains_when_the_email_is_sent(): void
    {
        $this->withoutVite();

        Config::set('app.timezone', 'UTC');
        Config::set('cart-scheduler.shift_assignment_notifications_enabled', false);
        Config::set('cart-scheduler.shift_reservation_duration', 1);
        Config::set('cart-scheduler.shift_reservation_duration_period', 'WEEK');
        Config::set('cart-scheduler.do_release_shifts_daily', false);
        Config::set('cart-scheduler.release_weekly_shifts_on_day', 'MON');
        Config::set('cart-scheduler.release_new_shifts_at_time', '12:00');

        $admin = User::factory()->adminRoleUser()->create();
        $this->allowSettingsUser($admin);

        $this->seed(EmailTemplateSeeder::class);
        $template = EmailTemplate::findByKey('shift-assignments-released');

        $this->actingAs($admin)
            ->get("/admin/emails/{$template->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Emails/Edit')
                ->has('template.trigger', fn (AssertableInertia $trigger) => $trigger
                    ->where('type', 'system')
                    ->where('enabled', false)
                    ->where('summary', fn (string $summary) => str_contains($summary, 'every Monday at 12:00 PM (UTC)')))
                ->has('template.recipients', fn (AssertableInertia $recipients) => $recipients
                    ->where('summary', fn (string $summary) => str_contains($summary, 'pre-assigned'))));
    }

    public function test_allowed_admin_can_update_system_email_template(): void
    {
        $admin = User::factory()->adminRoleUser()->create();
        $this->allowSettingsUser($admin);

        $this->seed(EmailTemplateSeeder::class);
        $template = EmailTemplate::findByKey('shift-assignments-released');

        $originalName = $template->name;
        $originalDescription = $template->description;

        $this->actingAs($admin)
            ->put("/admin/emails/{$template->id}", [
                'name' => 'Hacked Name',
                'subject' => 'Updated Subject for {{ user.name }}',
                'body' => "Dear {{ user.name }},\n\n{{ shifts }}",
                'description' => 'Should not be saved on app emails.',
            ])
            ->assertRedirect(route('admin.emails.edit', $template));

        $template->refresh();
        $this->assertSame('Updated Subject for {{ user.name }}', $template->subject);
        $this->assertStringContainsString('{{ shifts }}', $template->body);
        $this->assertSame($originalName, $template->name);
        $this->assertSame($originalDescription, $template->description);
    }

    public function test_unknown_placeholder_fails_validation(): void
    {
        $admin = User::factory()->adminRoleUser()->create();
        $this->allowSettingsUser($admin);

        $this->seed(EmailTemplateSeeder::class);
        $template = EmailTemplate::findByKey('shift-assignments-released');

        $this->actingAs($admin)
            ->put("/admin/emails/{$template->id}", [
                'subject' => $template->subject,
                'body' => 'Hello {{ unknown.placeholder }}',
            ])
            ->assertSessionHasErrors('body');
    }

    public function test_shift_loop_body_can_be_saved(): void
    {
        $admin = User::factory()->adminRoleUser()->create();
        $this->allowSettingsUser($admin);

        $this->seed(EmailTemplateSeeder::class);
        $template = EmailTemplate::findByKey('shift-assignments-released');

        $body = <<<'BODY'
{{#each shifts}}
**Date:** {{ shift.date }}
**Location:** {{ shift.location }}{{#if shift.location_map_url}} ([Map]({{ shift.location_map_url }})){{/if}}
{{#each shift.other_volunteers}}
- {{ volunteer.name }}
{{#empty}}
- None
{{/each}}
{{/each}}
BODY;

        $this->actingAs($admin)
            ->put("/admin/emails/{$template->id}", [
                'subject' => $template->subject,
                'body' => $body,
            ])
            ->assertRedirect(route('admin.emails.edit', $template));

        $this->assertSame($body, $template->refresh()->body);
    }

    public function test_shift_variable_outside_loop_fails_validation(): void
    {
        $admin = User::factory()->adminRoleUser()->create();
        $this->allowSettingsUser($admin);

        $this->seed(EmailTemplateSeeder::class);
        $template = EmailTemplate::findByKey('shift-assignments-released');

        $this->actingAs($admin)
            ->put("/admin/emails/{$template->id}", [
                'subject' => $template->subject,
                'body' => 'Date: {{ shift.date }}',
            ])
            ->assertSessionHasErrors('body');
    }

    public function test_placeholder_from_another_template_fails_validation(): void
    {
        $admin = User::factory()->adminRoleUser()->create();
        $this->allowSettingsUser($admin);

        $this->seed(EmailTemplateSeeder::class);
        $template = EmailTemplate::findByKey('user-account-created');

        $this->actingAs($admin)
            ->put("/admin/emails/{$template->id}", [
                'subject' => $template->subject,
                'body' => 'Period {{ period.label }}',
            ])
            ->assertSessionHasErrors('body');
    }

    public function test_renderer_replaces_placeholders_and_components(): void
    {
        $user = User::factory()->userRoleUser()->create(['name' => 'Jane Volunteer']);
        $shifts = collect([
            [
                'date' => 'Monday, 14 February 2023',
                'start_time' => '9:00 AM',
                'end_time' => '11:00 AM',
                'location' => 'Town Square',
                'other_volunteers' => [
                    ['name' => 'John Smith', 'mobile_phone' => '0400 000 000'],
                ],
            ],
        ]);

        $renderer = app(EmailTemplateRenderer::class);
        $rendered = $renderer->renderForMailable('shift-assignments-released', [
            'user' => $user,
            'shifts' => $shifts,
        ]);

        $this->assertStringContainsString('Jane Volunteer', $rendered['subject'].$rendered['body']);
        $this->assertStringContainsString('Town Square', $rendered['body']);
        $this->assertStringContainsString('John Smith', $rendered['body']);
        $this->assertSame('emails.dynamic-template', $rendered['view']);
    }

    public function test_shift_assignments_email_uses_database_template(): void
    {
        Mail::fake();

        $this->seed(EmailTemplateSeeder::class);

        $template = EmailTemplate::findByKey('shift-assignments-released');
        $template->update([
            'subject' => 'Custom Assignments for {{ user.name }}',
            'body' => "Hello {{ user.name }}\n\n{{ shifts }}",
        ]);

        $user = User::factory()->userRoleUser()->create(['name' => 'Assigned User']);
        $shifts = collect([
            [
                'date' => 'Monday, 14 February 2023',
                'start_time' => '9:00 AM',
                'end_time' => '11:00 AM',
                'location' => 'Park Location',
                'other_volunteers' => [],
            ],
        ]);

        Mail::to($user->email)->send(new ShiftAssignmentsReleased($user, $shifts));

        Mail::assertSent(ShiftAssignmentsReleased::class, function (ShiftAssignmentsReleased $mail) use ($user) {
            $built = $mail->build();

            return $mail->hasTo($user->email)
                && $built->subject === 'Custom Assignments for Assigned User';
        });
    }

    public function test_allowed_admin_can_preview_email_template(): void
    {
        $admin = User::factory()->adminRoleUser()->create();
        $this->allowSettingsUser($admin);

        $this->seed(EmailTemplateSeeder::class);
        $template = EmailTemplate::findByKey('shift-assignments-released');

        $response = $this->actingAs($admin)
            ->postJson('/admin/emails/preview', [
                'key' => $template->key,
                'subject' => $template->subject,
                'body' => $template->body,
            ])
            ->assertOk()
            ->assertJsonStructure(['subject', 'html'])
            ->json();

        $this->assertSame(
            app(GeneralSettings::class)->siteName.' Shift Assignments: 1 February 2026 – 28 February 2026',
            $response['subject'],
        );
        $this->assertStringContainsString('Jane Volunteer', $response['html']);
        $this->assertStringContainsString('Town Square', $response['html']);
        $this->assertStringContainsString('Meet at the north entrance near the cart.', $response['html']);
        $this->assertStringContainsString('https://www.google.com/maps?q=-37.8136,144.9631', $response['html']);
        $this->assertMatchesRegularExpression('/Date:.*?<br/is', $response['html']);
        $this->assertMatchesRegularExpression('/Location:.*?<br/is', $response['html']);
        $this->assertMatchesRegularExpression('/Start Time:.*?<br/is', $response['html']);
        $this->assertMatchesRegularExpression('/Finish Time:.*?<br/is', $response['html']);
    }

    public function test_non_allowed_admin_cannot_preview_email_template(): void
    {
        $admin = User::factory()->adminRoleUser()->create();
        $other = User::factory()->adminRoleUser()->create();

        $generalSettings = $this->app->make(GeneralSettings::class);
        $generalSettings->allowedSettingsUsers = [$other->getKey()];
        $generalSettings->save();

        $this->actingAs($admin)
            ->postJson('/admin/emails/preview', [
                'key' => 'shift-assignments-released',
                'subject' => 'Test',
                'body' => 'Hello {{ user.name }}',
            ])
            ->assertNotFound();
    }
}
