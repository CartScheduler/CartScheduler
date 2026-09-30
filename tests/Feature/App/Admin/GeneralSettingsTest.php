<?php

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

uses(RefreshDatabase::class);

test('admin can edit general settings', function () {
    $admin = User::factory()->adminRoleUser()->create();

    $generalSettings = $this->app->make(GeneralSettings::class);

    $generalSettings->siteName = 'Old Site Name';
    $generalSettings->systemShiftStartHour = 10;
    $generalSettings->systemShiftEndHour = 13;
    $generalSettings->enableUserAvailability = false;
    $generalSettings->enableUserLocationChoices = false;
    $generalSettings->enableShiftRemoveConfirm = false;
    $generalSettings->shiftRemoveConfirmMessage = 'Have you contacted all others on your shift?';
    $generalSettings->save();

    $this->actingAs($admin)
        ->putJson('/admin/general-settings', [
            'siteName' => 'New Site Name',
            'systemShiftStartHour' => 12,
            'systemShiftEndHour' => 15,
            'enableUserAvailability' => true,
            'enableUserLocationChoices' => true,
            'enableShiftRemoveConfirm' => true,
            'shiftRemoveConfirmMessage' => 'Custom confirmation message',
        ])
        ->assertRedirect(route('admin.settings'));

    $generalSettings->refresh();
    expect($generalSettings->siteName)->toBe('New Site Name');
    expect($generalSettings->systemShiftStartHour)->toBe(12);
    expect($generalSettings->systemShiftEndHour)->toBe(15);
    expect($generalSettings->enableUserAvailability)->toBeTrue();
    expect($generalSettings->enableUserLocationChoices)->toBeTrue();
    expect($generalSettings->enableShiftRemoveConfirm)->toBeTrue();
    expect($generalSettings->shiftRemoveConfirmMessage)->toBe('Custom confirmation message');
});

test('remove reservation confirmation message is not required when confirmation disabled', function () {
    $admin = User::factory()->adminRoleUser()->create();

    $this->actingAs($admin)
        ->putJson('/admin/general-settings', [
            'siteName' => 'Site Name',
            'systemShiftStartHour' => 8,
            'systemShiftEndHour' => 17,
            'enableUserAvailability' => false,
            'enableUserLocationChoices' => false,
            'enableShiftRemoveConfirm' => false,
            'shiftRemoveConfirmMessage' => '',
        ])
        ->assertRedirect(route('admin.settings'));
});

test('remove reservation confirmation message is required when confirmation enabled', function () {
    $admin = User::factory()->adminRoleUser()->create();

    $this->actingAs($admin)
        ->putJson('/admin/general-settings', [
            'siteName' => 'Site Name',
            'systemShiftStartHour' => 8,
            'systemShiftEndHour' => 17,
            'enableUserAvailability' => false,
            'enableUserLocationChoices' => false,
            'enableShiftRemoveConfirm' => true,
            'shiftRemoveConfirmMessage' => '',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['shiftRemoveConfirmMessage']);
});

test('admin can update allowed settings users', function () {
    $admin = User::factory()->adminRoleUser()->create();

    $generalSettings = $this->app->make(GeneralSettings::class);

    $generalSettings->allowedSettingsUsers = [1];
    $generalSettings->save();

    $this->actingAs($admin)
        ->putJson('/admin/allowed-settings-users', [
            'allowedSettingsUsers' => [1, 2],
        ])
        ->assertRedirect(route('admin.settings'));

    $generalSettings->refresh();
    expect($generalSettings->allowedSettingsUsers)->toBe([1, 2]);
});

test('admin can view general settings', function () {
    $admin = User::factory()->adminRoleUser()->create();

    $generalSettings = $this->app->make(GeneralSettings::class);

    $generalSettings->siteName = 'Old Site Name';
    $generalSettings->systemShiftStartHour = 10;
    $generalSettings->systemShiftEndHour = 13;
    $generalSettings->enableUserAvailability = false;
    $generalSettings->enableUserLocationChoices = false;
    $generalSettings->enableShiftRemoveConfirm = true;
    $generalSettings->shiftRemoveConfirmMessage = 'Have you contacted all others on your shift?';
    $generalSettings->currentVersion = '1.0.0';
    $generalSettings->availableVersion = '1.0.1';
    $generalSettings->allowedSettingsUsers = [$admin->getKey()];
    $generalSettings->save();

    $this->actingAs($admin)
        ->get('/admin/settings')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Settings/Show')
            ->has('settings', fn (AssertableInertia $data) => $data
                ->where('siteName', 'Old Site Name')
                ->where('systemShiftStartHour', 10)
                ->where('systemShiftEndHour', 13)
                ->where('enableUserAvailability', false)
                ->where('enableUserLocationChoices', false)
                ->where('enableShiftRemoveConfirm', true)
                ->where('shiftRemoveConfirmMessage', 'Have you contacted all others on your shift?')
                ->where('currentVersion', '1.0.0')
                ->where('availableVersion', '1.0.1')
                ->where('allowedSettingsUsers', [$admin->getKey()])
            )
        );
});

test('non allowed admin cannot view general settings', function () {
    $admin = User::factory()->adminRoleUser()->create();
    $admin2 = User::factory()->adminRoleUser()->create();

    $generalSettings = $this->app->make(GeneralSettings::class);

    $generalSettings->allowedSettingsUsers = [$admin2->getKey()];
    $generalSettings->save();

    $this->actingAs($admin)
        ->get('/admin/settings')
        ->assertNotFound();
});

test('admin can check for update', function () {
    $admin = User::factory()->adminRoleUser()->create();

    $this->mock(
        CheckAvailableVersions::class,
        fn (MockInterface $mock) => $mock
            ->expects('execute')->with(true, true)->andReturn('v2.0.0')->twice()
    );

    Config::set('streamline.installed_version', 'v1.0.0');
    $this->actingAs($admin)
        ->getJson('/admin/check-update')
        ->assertOk()
        ->assertContent('1');

    Config::set('streamline.installed_version', 'v3.0.0');
    $this->actingAs($admin)
        ->getJson('/admin/check-update')
        ->assertOk()
        ->assertNoContent(200);
});

test('admin can check for beta update', function () {
    $admin = User::factory()->adminRoleUser()->create();

    $this->mock(
        CheckAvailableVersions::class,
        fn (MockInterface $mock) => $mock
            ->expects('execute')->with(true, false)->andReturn('v2.0.0b')
    );

    $this->actingAs($admin)
        ->getJson('/admin/check-update?beta=true')
        ->assertOk()
        ->assertContent('1');
});

test('run system update', function () {
    $admin = User::factory()->adminRoleUser()->create();

    $stream = fopen('php://memory', 'wb');

    Artisan::expects('call')
        ->withSomeOfArgs('streamline:run-update')
        ->andReturnUsing(function () use ($stream) {
            // Write some output to the stream
            fwrite($stream, "Some test data\n");
            // This should simulate some output that happens during the command execution
            echo stream_get_contents($stream, null, 0);

            return 0;
        });

    $this->app->bind(FilterAnsiEscapeSequencesStreamedOutput::class,
        fn (Application $app) => new FilterAnsiEscapeSequencesStreamedOutput(
            $stream,
            OutputInterface::VERBOSITY_VERBOSE,
            true,
        ));

    $this->actingAs($admin)
        ->postJson('/admin/do-update')
        ->assertStreamed()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertHeader('X-Accel-Buffering', 'no')
        ->assertHeader('Cache-Control', 'no-cache, private')
        ->assertStreamedContent("Running Software Update... (Version: v0.0.5).\nNOTE: THIS MAY TAKE A WHILE...\nSome test data\n")
        ->assertOk();

    fclose($stream);
});

test('run system update when beta has been selected for update', function () {
    GeneralSettings::fake([
        'availableVersion' => 'v2.0.0b',
    ]);

    Artisan::expects('call')
        ->withSomeOfArgs('streamline:run-update', ['--force' => true, '--install-version' => 'v2.0.0b'])
        ->andReturn(0)
        ->once();

    $admin = User::factory()->adminRoleUser()->create();

    $this->actingAs($admin)
        ->postJson('/admin/do-update')
        ->assertStreamed()
        ->assertOk()
        ->assertStreamedContent("Running Software Update... (Version: v2.0.0b).\nNOTE: THIS MAY TAKE A WHILE...\n");
});

test('run system update but failed', function () {
    Artisan::expects('call')
        ->withSomeOfArgs('streamline:run-update')
        ->andReturn(1);

    Log::expects('error')->with('Command streaming failed: Command failed');

    $admin = User::factory()->adminRoleUser()->create();

    $this->actingAs($admin)
        ->postJson('/admin/do-update')
        ->assertStreamed()
        ->assertOk()
        ->assertStreamedContent("Running Software Update... (Version: v0.0.5).\nNOTE: THIS MAY TAKE A WHILE...\nError: Command failed");
});
