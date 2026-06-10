<?php

namespace Tests\Feature\App\Admin;

use App\Lib\FilterAnsiEscapeSequencesStreamedOutput;
use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia;
use Mockery\MockInterface;
use Pixelated\Streamline\Actions\CheckAvailableVersions;
use Symfony\Component\Console\Output\OutputInterface;
use Tests\TestCase;

class GeneralSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_edit_general_settings(): void
    {
        $admin = User::factory()->adminRoleUser()->create();

        $generalSettings = $this->app->make(GeneralSettings::class);

        $generalSettings->siteName                  = 'Old Site Name';
        $generalSettings->systemShiftStartHour      = 10;
        $generalSettings->systemShiftEndHour        = 13;
        $generalSettings->enableUserAvailability    = false;
        $generalSettings->enableUserLocationChoices = false;
        $generalSettings->save();

        $this->actingAs($admin)
            ->putJson("/admin/general-settings", [
                'siteName'                  => 'New Site Name',
                'systemShiftStartHour'      => 12,
                'systemShiftEndHour'        => 15,
                'enableUserAvailability'    => true,
                'enableUserLocationChoices' => true,
            ])
            ->assertRedirect(route('admin.settings'));

        $generalSettings->refresh();
        $this->assertSame('New Site Name', $generalSettings->siteName);
        $this->assertSame(12, $generalSettings->systemShiftStartHour);
        $this->assertSame(15, $generalSettings->systemShiftEndHour);
        $this->assertTrue($generalSettings->enableUserAvailability);
        $this->assertTrue($generalSettings->enableUserLocationChoices);
    }

    public function test_admin_can_update_allowed_settings_users(): void
    {
        $admin = User::factory()->adminRoleUser()->create();

        $generalSettings = $this->app->make(GeneralSettings::class);

        $generalSettings->allowedSettingsUsers = [1];
        $generalSettings->save();

        $this->actingAs($admin)
            ->putJson("/admin/allowed-settings-users", [
                'allowedSettingsUsers' => [1, 2],
            ])
            ->assertRedirect(route('admin.settings'));

        $generalSettings->refresh();
        $this->assertSame([1, 2], $generalSettings->allowedSettingsUsers);
    }

    public function test_admin_can_view_general_settings(): void
    {
        $admin = User::factory()->adminRoleUser()->create();

        $generalSettings = $this->app->make(GeneralSettings::class);

        $generalSettings->siteName                  = 'Old Site Name';
        $generalSettings->systemShiftStartHour      = 10;
        $generalSettings->systemShiftEndHour        = 13;
        $generalSettings->enableUserAvailability    = false;
        $generalSettings->enableUserLocationChoices = false;
        $generalSettings->currentVersion            = '1.0.0';
        $generalSettings->availableVersion          = '1.0.1';
        $generalSettings->allowedSettingsUsers      = [$admin->getKey()];
        $generalSettings->save();

        $this->actingAs($admin)
            ->get("/admin/settings")
            ->assertInertia(fn(AssertableInertia $page) => $page
                ->component('Admin/Settings/Show')
                ->has('settings', fn(AssertableInertia $data) => $data
                    ->where('siteName', 'Old Site Name')
                    ->where('systemShiftStartHour', 10)
                    ->where('systemShiftEndHour', 13)
                    ->where('enableUserAvailability', false)
                    ->where('enableUserLocationChoices', false)
                    ->where('currentVersion', '1.0.0')
                    ->where('availableVersion', '1.0.1')
                    ->where('allowedSettingsUsers', [$admin->getKey()])
                )
            );
    }

    public function test_non_allowed_admin_cannot_view_general_settings(): void
    {
        $admin  = User::factory()->adminRoleUser()->create();
        $admin2 = User::factory()->adminRoleUser()->create();

        $generalSettings = $this->app->make(GeneralSettings::class);

        $generalSettings->allowedSettingsUsers = [$admin2->getKey()];
        $generalSettings->save();

        $this->actingAs($admin)
            ->get("/admin/settings")
            ->assertNotFound();
    }

    public function test_admin_can_check_for_update(): void
    {
        $admin = User::factory()->adminRoleUser()->create();

        $this->mock(
            CheckAvailableVersions::class,
            fn(MockInterface $mock) => $mock
                ->expects('execute')->with(true, true)->andReturn('v2.0.0')->twice()
        );

        Config::set('streamline.installed_version', 'v1.0.0');
        $this->actingAs($admin)
            ->getJson("/admin/check-update")
            ->assertOk()
            ->assertContent('1');

        Config::set('streamline.installed_version', 'v3.0.0');
        $this->actingAs($admin)
            ->getJson("/admin/check-update")
            ->assertOk()
            ->assertNoContent(200);
    }

    public function test_admin_can_check_for_beta_update(): void
    {
        $admin = User::factory()->adminRoleUser()->create();

        $this->mock(
            CheckAvailableVersions::class,
            fn(MockInterface $mock) => $mock
                ->expects('execute')->with(true, false)->andReturn('v2.0.0b')
        );

        $this->actingAs($admin)
            ->getJson("/admin/check-update?beta=true")
            ->assertOk()
            ->assertContent('1');
    }

    public function test_run_system_update(): void
    {
        $admin = User::factory()->adminRoleUser()->create();

        $stream = fopen('php://memory', 'wb');

        Artisan::expects('call')
            ->withSomeOfArgs('streamline:run-update')
            ->andReturnUsing(function () use ($stream) {
                // Write some output to the stream
                fwrite($stream, "Some test data\n");
                // This should simulate some output that happens during the command execution
                echo stream_get_contents($stream,null, 0);
                return 0;
            });


        $this->app->bind(FilterAnsiEscapeSequencesStreamedOutput::class,
            fn(Application $app) => new FilterAnsiEscapeSequencesStreamedOutput(
                $stream,
                OutputInterface::VERBOSITY_VERBOSE,
                true,
            ));

        $this->actingAs($admin)
            ->postJson("/admin/do-update")
            ->assertStreamed()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->assertHeader('X-Accel-Buffering', 'no')
            ->assertHeader('Cache-Control', 'no-cache, private')
            ->assertStreamedContent("Running Software Update... (Version: v0.0.5).\nNOTE: THIS MAY TAKE A WHILE...\nSome test data\n")
            ->assertOk();

        fclose($stream);
    }

    public function test_run_system_update_when_beta_has_been_selected_for_update(): void
    {
        GeneralSettings::fake([
            'availableVersion' => 'v2.0.0b',
        ]);

        Artisan::expects('call')
            ->withSomeOfArgs('streamline:run-update', ['--force' => true, '--install-version' => 'v2.0.0b'])
            ->andReturn(0)
            ->once();

        $admin = User::factory()->adminRoleUser()->create();

        $this->actingAs($admin)
            ->postJson("/admin/do-update")
            ->assertStreamed()
            ->assertOk()
            ->assertStreamedContent("Running Software Update... (Version: v2.0.0b).\nNOTE: THIS MAY TAKE A WHILE...\n");
    }

    public function test_run_system_update_but_failed(): void
    {
        Artisan::expects('call')
            ->withSomeOfArgs('streamline:run-update')
            ->andReturn(1);

        Log::expects('error')->with('Command streaming failed: Command failed');

        $admin = User::factory()->adminRoleUser()->create();

        $this->actingAs($admin)
            ->postJson("/admin/do-update")
            ->assertStreamed()
            ->assertOk()
            ->assertStreamedContent("Running Software Update... (Version: v0.0.5).\nNOTE: THIS MAY TAKE A WHILE...\nError: Command failed");
    }
}
