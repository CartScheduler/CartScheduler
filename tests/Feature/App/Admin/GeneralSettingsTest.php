<?php

use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia;

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
    $generalSettings->allowedSettingsUsers = [$admin->getKey()];
    $generalSettings->save();

    Config::set('cart-scheduler.version', 'v1.2.3');

    $this->actingAs($admin)
        ->get('/admin/settings')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Settings/Show')
            ->where('version', 'v1.2.3')
            ->has('settings', fn (AssertableInertia $data) => $data
                ->where('siteName', 'Old Site Name')
                ->where('systemShiftStartHour', 10)
                ->where('systemShiftEndHour', 13)
                ->where('enableUserAvailability', false)
                ->where('enableUserLocationChoices', false)
                ->where('enableShiftRemoveConfirm', true)
                ->where('shiftRemoveConfirmMessage', 'Have you contacted all others on your shift?')
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
