<?php

namespace Tests\Feature\App\Admin;

use App\Enums\Role;
use App\Mail\UserAccountCreated;
use App\Models\Location;
use App\Models\User;
use App\Models\UserAvailability;
use App\Models\UserVacation;
use App\Settings\GeneralSettings;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class UsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_retrieve_all_users(): void
    {
        $admin = User::factory()->enabled()->adminRoleUser()->create();
        User::factory()->enabled()->count(5)->create();

        $this->actingAs($admin)
            ->get("/admin/users")
            ->assertOk()
            ->assertInertia(fn(AssertableInertia $page) => $page
                ->component('Admin/Users/List')
                ->has('users', 6)
            );
    }

    public function test_admin_can_show_create_user_form(): void
    {
        $admin = User::factory()->enabled()->adminRoleUser()->create();

        $this->actingAs($admin)
            ->getJson("/admin/users/create")
            ->assertOk()
            ->assertInertia(fn(AssertableInertia $page) => $page
                ->component('Admin/Users/Add')
            );
    }

    public function test_admin_can_show_edit_user_form(): void
    {
        $admin = User::factory()->enabled()->adminRoleUser()->create();

        $mondayHours = range(8, 12);

        $wife    = User::factory()->enabled()->female()->create();
        $husband = User::factory()
            ->enabled()
            ->male()
            ->state(['spouse_id' => $wife->id])
            ->has(
                UserAvailability::factory()
                    ->state([
                        'day_monday'  => $mondayHours,
                        'num_mondays' => 3,
                    ])
                , 'availability')
            ->has(UserVacation::factory(), 'vacations')
            ->has(Location::factory()->count(2), 'rosterLocations')
            ->create();

        $this->actingAs($admin)
            ->get("/admin/users/$husband->id/edit")
            ->assertInertia(fn(AssertableInertia $page) => $page
                ->component('Admin/Users/Edit')
                ->has('editUser', fn(AssertableInertia $data) => $data
                    ->where('id', $husband->id)
                    ->where('spouse.name', $husband->spouse->name)
                    ->where('spouse.id', $husband->spouse->id)
                    ->where('selectedLocations.0', $husband->rosterLocations[0]->id)
                    ->where('vacations.0.id', $husband->vacations[0]->id)
                    ->where('availability.day_monday', $mondayHours)
                    ->where('availability.comments', $husband->availability->comments)
                    ->etc()
                )
            );
    }

    public function test_admin_can_add_new_user_and_user_receives_email(): void
    {
        $admin = User::factory()->adminRoleUser()->state(['is_enabled' => true])->create();
        $this->assertDatabaseCount('users', 1);
        $user = User::factory()->enabled()->makeOne();
        $this->assertDatabaseCount('users', 1);

        $userData = $user->makeVisible('role', 'mobile_phone')->toArray();

        Mail::fake();
        $this->actingAs($admin)
            ->postJson("/admin/users/", $userData)
            ->assertRedirect()
            ->assertSessionHas('flash.message', "$user->name was successfully created.");
        Mail::assertSent(UserAccountCreated::class, 1);

        $this->assertDatabaseCount('users', 2);
    }

    public function test_admin_adds_new_user_with_bad_data_fails(): void
    {
        $admin = User::factory()->adminRoleUser()->state(['is_enabled' => true])->create();
        $this->assertDatabaseCount('users', 1);

        $userData = [
            'name'                => '',
            'email'               => 'invalid email',
            'role'                => 'invalid role',
            'gender'              => 'invalid gender',
            'mobile_phone'        => 'invalid phone',
            'year_of_birth'       => 'invalid year',
            'appointment'         => 'invalid appointment',
            'serving_as'          => 'invalid serving as',
            'marital_status'      => 'invalid marital status',
            'responsible_brother' => 'invalid responsible brother',
            'is_unrestricted'     => 'invalid unrestricted',
        ];

        Mail::fake();
        $this->actingAs($admin)
            ->postJson("/admin/users/", $userData)
            ->assertUnprocessable()
            ->assertInvalid([
                'name',
                'email',
                'role',
                'gender',
                'mobile_phone',
                'year_of_birth',
                'appointment',
                'serving_as',
                'marital_status',
                'responsible_brother',
                'is_unrestricted',
            ]);
        $this->assertDatabaseCount('users', 1);
        Mail::assertNothingSent();
    }

    public function test_restrict_admin_user_disallow_remove_admin_rights(): void
    {
        $admin       = User::factory()->enabled()->adminRoleUser()->create();
        $demotedUser = User::factory()->enabled()->adminRoleUser()->create();

        GeneralSettings::fake(['allowedSettingsUsers' => [$admin->id, $demotedUser->id]]);

        $this->actingAs($admin)
            ->putJson("/admin/users/$demotedUser->id", [
                'id'                  => $demotedUser->id,
                'name'                => $demotedUser->name,
                'email'               => $demotedUser->email,
                'mobile_phone'        => $demotedUser->mobile_phone,
                'role'                => Role::Admin->value,
                'gender'              => $demotedUser->gender,
                'year_of_birth'       => $demotedUser->year_of_birth,
                'appointment'         => $demotedUser->appointment,
                'serving_as'          => $demotedUser->serving_as,
                'marital_status'      => $demotedUser->marital_status,
                'responsible_brother' => $demotedUser->responsible_brother,
                'is_unrestricted'     => false,
            ])
            ->assertInvalid(['is_unrestricted' => 'Restricted users cannot be an administrator.']);

        $this->assertDatabaseHas('users',
            ['id' => $demotedUser->id, 'role' => Role::Admin->value, 'is_unrestricted' => true]);
    }

    public function test_admin_can_edit_user_and_test_bad_email(): void
    {
        $admin    = User::factory()->adminRoleUser()->state(['is_enabled' => true])->create();
        $oldEmail = 'foo@example.com';
        $user     = User::factory()->enabled()->state(['email' => $oldEmail])->create();
        $this->assertEquals($oldEmail, $user->fresh()->email);

        $userData          = $user->makeVisible('role', 'mobile_phone')->toArray();
        $newEmail          = 'invalid email';
        $userData['email'] = $newEmail;

        $this->actingAs($admin)
            ->putJson("/admin/users/{$user->getKey()}", $userData)
            ->assertUnprocessable();

        $newEmail          = 'bar@example.com';
        $userData['email'] = $newEmail;

        $this->actingAs($admin)
            ->putJson("/admin/users/{$user->getKey()}", $userData)
            ->assertRedirect("/admin/users/{$user->getKey()}/edit")
            ->assertSessionHas('flash.message', "$user->name was successfully modified.");

        $this->assertEquals($newEmail, $user->fresh()->email);
    }

    public function test_admin_can_delete_user(): void
    {
        $admin = User::factory()->adminRoleUser()->state(['is_enabled' => true])->create();
        $user  = User::factory()->enabled()->create();
        $this->assertDatabaseCount('users', 2);

        $this->actingAs($admin)
            ->deleteJson("/admin/users/{$user->getKey()}")
            ->assertRedirect()
            ->assertSessionHas('flash.message', "$user->name was successfully deleted.");
        $this->assertDatabaseCount('users', 1);

        $this->actingAs($admin)
            ->deleteJson("/admin/users/9999999")
            ->assertnotFound();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_admin_can_send_password_reset_email(): void
    {
        $admin = User::factory()->adminRoleUser()->state(['is_enabled' => true])->create();
        $user  = User::factory()->enabled()->create();

        // Note: password reset uses the 'notifications' feature of Laravel, not the 'mail' feature
        Mail::fake();
        Notification::fake();
        $this->actingAs($admin)
            ->postJson("/admin/resend-welcome-email?user_id={$user->getKey()}")
            ->assertOk()
            ->assertJsonPath('message', 'A password reset link has been sent to the user.');
        Notification::assertSentToTimes($user, ResetPassword::class);
        Mail::assertNothingSent();
    }

    public function test_password_reset_too_many_requests(): void
    {
        $admin = User::factory()->adminRoleUser()->state(['is_enabled' => true])->create();
        $user  = User::factory()->enabled()->create();

        Mail::fake();
        Notification::fake();
        Password::shouldReceive('sendResetLink')
            ->andReturn(Password::RESET_LINK_SENT, Password::RESET_LINK_SENT, Password::RESET_THROTTLED);

        for ($i = 0; $i < 3; $i++) {
            // 3rd request should fail
            $expected = $i < 2 ? 'has been sent' : 'too many password reset attempts';
            $this->actingAs($admin)
                ->postJson("/admin/resend-welcome-email?user_id={$user->getKey()}")
                ->assertStatus($i < 2 ? 200 : 429)
                ->assertContainsStringIgnoringCase('message', $expected);
        }
        Mail::assertNothingSent();
    }

    public function test_password_reset_unhandled_password_response(): void
    {
        $admin = User::factory()->adminRoleUser()->state(['is_enabled' => true])->create();
        $user  = User::factory()->enabled()->create();

        Mail::fake();
        Notification::fake();
        Password::shouldReceive('sendResetLink')
            ->andReturn(Password::INVALID_USER);

        // 3rd request should fail
        $this->actingAs($admin)
            ->postJson("/admin/resend-welcome-email?user_id={$user->getKey()}")
            ->assertserverError()
            ->assertContainsStringIgnoringCase('message', 'unknown error');
        Mail::assertNothingSent();
    }

    public function test_admin_can_resend_welcome_email(): void
    {
        $admin = User::factory()->adminRoleUser()->state(['is_enabled' => true])->create();
        $user  = User::factory()->enabled()->state(['password' => null])->create();

        Mail::fake();
        $this->actingAs($admin)
            ->postJson("/admin/resend-welcome-email?user_id={$user->getKey()}")
            ->assertOk()
            ->assertJsonPath('message', 'Welcome email was sent');

        Mail::assertSent(UserAccountCreated::class, 1);
    }

    public function test_resend_welcome_email_is_correct(): void
    {
        $admin = User::factory()->adminRoleUser()->state(['is_enabled' => true])->create();
        $user  = User::factory()->enabled()->state(['password' => null])->create();

        Mail::fake();
        $this->actingAs($admin)
            ->postJson("/admin/resend-welcome-email?user_id={$user->getKey()}")
            ->assertOk()
            ->assertJsonPath('message', 'Welcome email was sent');

        Mail::assertSent(UserAccountCreated::class, 1);
    }

    public function test_welcome_email_is_correct(): void
    {
        $user = User::factory()->enabled()->state(['password' => null])->create();

        $textMatch = "Dear $user->name, an account has been created for you on the " . config('app.name') . " Public Witnessing web application.";
        $mailable  = (new UserAccountCreated($user))
            ->assertHasSubject(config('app.name') . ' Account Activation')
            ->assertSeeInHtml($textMatch)
            ->assertSeeInText($textMatch);

        $render = $mailable->render();
        $hashed = Str::of($render)->match('/set-password\/\d+\/([a-zA-Z0-9]+)/');
        $this->assertTrue(Password::tokenExists($user, $hashed));
    }

    public function test_validations_are_working(): void
    {
        $admin = User::factory()->adminRoleUser()->state(['is_enabled' => true])->create();

        $userData = [
            'name'                => str_repeat('a', 256),
            'email'               => $admin->email,
            'role'                => 'peanut',
            'gender'              => 'gorilla',
            'mobile_phone'        => 'my phone number',
            'year_of_birth'       => '1900',
            'appointment'         => 'peanut',
            'serving_as'          => 'hyena',
            'marital_status'      => 'watermelon',
            'responsible_brother' => 'yes',
            'is_unrestricted'     => 'no',
        ];

        $this->actingAs($admin)
            ->postJson("/admin/users/", $userData)
            ->assertUnprocessable()
            ->assertInvalid(array_keys($userData));

        $this->assertDatabaseCount('users', 1);
    }

    public function test_duplicate_user_is_not_created(): void
    {
        $admin = User::factory()->adminRoleUser()->state(['is_enabled' => true])->create();
        $user  = User::factory()->enabled()->create();
        $user2 = User::factory()->enabled()->state(['email' => $user->email])->makeOne();

        $this->actingAs($admin)
            ->postJson("/admin/users/", $user2->toArray())
            ->assertUnprocessable()
            ->assertInvalid(['email']);

        $this->assertDatabaseCount('users', 2);
    }

    public function test_phone_number_is_created_properly(): void
    {
        $admin = User::factory()->adminRoleUser()->state(['is_enabled' => true])->create();
        $user  = User::factory()->enabled()->makeOne();

        $userData                 = $user->makeVisible('role')->toArray();
        $userData['mobile_phone'] = '1 234 4567 89 ';

        $this->actingAs($admin)
            ->postJson("/admin/users/", $userData)
            ->assertRedirect();

        $this->assertDatabaseCount('users', 2);

        $user = User::firstWhere('name', $userData['name']);
        $this->assertEquals('1234 456 789', $user->mobile_phone);

        $storedDbValue = DB::table('users')->where('id', $user->id)->value('mobile_phone');
        $this->assertEquals('1234456789', $storedDbValue);
    }

    public function test_phone_number_is_updated_properly(): void
    {
        $admin = User::factory()->adminRoleUser()->state(['is_enabled' => true])->create();
        $user  = User::factory()->enabled()->state(['mobile_phone' => '1111111111'])->create();

        $userData                 = $user->makeVisible('role')->toArray();
        $userData['mobile_phone'] = '1 234 4567 89 ';

        $this->actingAs($admin)
            ->putJson("/admin/users/{$user->getKey()}", $userData)
            ->assertRedirect("/admin/users/{$user->getKey()}/edit");

        $this->assertEquals('1234 456 789', $user->fresh()->mobile_phone);

        $userData['mobile_phone'] = '+61412345678';

        $this->actingAs($admin)
            ->putJson("/admin/users/{$user->getKey()}", $userData)
            ->assertRedirect("/admin/users/{$user->getKey()}/edit")
            ->assertSessionHas('flash.message', "$user->name was successfully modified.");

        $storedDbValue = DB::table('users')->where('id', $user->id)->value('mobile_phone');
        $this->assertEquals('0412345678', $storedDbValue);

        $this->assertEquals('0412 345 678', $user->fresh()->mobile_phone);
    }

    public function test_admin_can_add_update_delete_user_vacations(): void
    {
        $admin = User::factory()->adminRoleUser()->state(['is_enabled' => true])->create();
        $user  = User::factory()->enabled()->create();

        $vacationData = [
            'user_id'   => $user->getKey(),
            'vacations' => [
                ['start_date' => '2023-01-01', 'end_date' => '2023-01-15', 'description' => 'Testing'],
                ['start_date' => '2023-02-01', 'end_date' => '2023-02-15', 'description' => 'Testing 2'],
            ],
        ];

        $this->actingAs($admin)
            ->putJson("/user/vacations", $vacationData)
            ->assertRedirect("/admin/users/{$user->getKey()}/edit");

        $user->refresh()->load(['vacations']);
        $this->assertCount(2, $user->vacations);
        $this->assertSame('Testing', $user->vacations[0]->description);
        $this->assertSame('Testing 2', $user->vacations[1]->description);
        $vacation              = $user->vacations[0];
        $vacation->start_date  = '2023-01-07';
        $vacation->description = 'Testing Updated';
        $this->actingAs($admin)
            ->putJson("/user/vacations", [
                'user_id'   => $user->getKey(),
                'vacations' => [$vacation->toArray()],
            ])
            ->assertRedirect("/admin/users/{$user->getKey()}/edit");

        $user->refresh()->load(['vacations']);
        $this->assertCount(2, $user->vacations);
        $this->assertSame('2023-01-07', $user->vacations[0]->start_date);
        $this->assertSame('Testing Updated', $user->vacations[0]->description);
        $this->assertSame('Testing 2', $user->vacations[1]->description);

        $this->actingAs($admin)
            ->putJson("/user/vacations", [
                'user_id'          => $user->getKey(),
                'deletedVacations' => [['id' => $vacation->getKey()]],
            ])
            ->assertRedirect("/admin/users/{$user->getKey()}/edit");

        $user->refresh()->load(['vacations']);
        $this->assertCount(1, $user->vacations);
        $this->assertSame('Testing 2', $user->vacations[0]->description);
    }

    public function test_admin_can_add_update_user_regular_availability(): void
    {
        $settings                         = app()->make(GeneralSettings::class);
        $settings->enableUserAvailability = true;
        $settings->save();

        $admin        = User::factory()->adminRoleUser()->state(['is_enabled' => true])->create();
        $user         = User::factory()->enabled()->create();
        $availability = UserAvailability::factory()->wedThuTenToOne()->state(['user_id' => $user->getKey()])->makeOne();

        $this->assertDatabaseEmpty('user_availabilities');
        $this->actingAs($admin)
            ->putJson("/user/availability", $availability->toArray())
            ->assertRedirect("/admin/users/{$user->getKey()}/edit");
        $this->assertDatabaseCount('user_availabilities', 1);
        $this->assertDatabaseHas('user_availabilities',
            Arr::except($availability->getAttributes(), ['created_at', 'updated_at']));

        $availability->num_wednesdays = 0;
        $availability->num_fridays    = 1;
        $availability->day_wednesday  = null;
        $availability->day_friday     = range(10, 13);
        $availability->comments       = "Testing";

        $this->actingAs($admin)
            ->putJson("/user/availability", $availability->toArray())
            ->assertRedirect("/admin/users/{$user->getKey()}/edit");
        $this->assertDatabaseHas('user_availabilities',
            Arr::except($availability->getAttributes(), ['created_at', 'updated_at']));
    }

    public function test_admin_can_maintain_user_location_choices(): void
    {
        $settings                            = app()->make(GeneralSettings::class);
        $settings->enableUserLocationChoices = true;
        $settings->save();

        $admin     = User::factory()->adminRoleUser()->state(['is_enabled' => true])->create();
        $user      = User::factory()->enabled()->create();
        $locations = Location::factory()->count(6)->create();

        $choiceData = [
            'user_id'           => $user->getKey(),
            'selectedLocations' => [
                $locations[0]->getKey(),
                $locations[2]->getKey(),
            ],
        ];

        $this->actingAs($admin)
            ->putJson("/user/available-locations", $choiceData)
            ->assertRedirect("/admin/users/{$user->getKey()}/edit")
            ->assertSessionHas('flash.banner', "volunteer preferred locations have been updated.");

        $user->refresh()->load(['rosterLocations']);
        $this->assertCount(2, $user->rosterLocations);

        $rosterLocations = $user->rosterLocations->pluck('name');
        $this->assertContains($locations[0]->name, $rosterLocations);
        $this->assertContains($locations[2]->name, $rosterLocations);
        $this->assertNotContains($locations[1]->name, $rosterLocations, 'Verify data is not duplicated');

        $choiceData['selectedLocations'][1] = $locations[1]->getKey();

        $this->actingAs($admin)
            ->putJson("/user/available-locations", $choiceData)
            ->assertRedirect("/admin/users/{$user->getKey()}/edit");

        $user->refresh()->load(['rosterLocations']);
        $rosterLocations = $user->rosterLocations->pluck('name');
        $this->assertContains($locations[0]->name, $rosterLocations);
        $this->assertContains($locations[1]->name, $rosterLocations);
    }

    public function test_admin_cant_maintain_disabled_feature_of_user_location_choices(): void
    {
        $admin     = User::factory()->adminRoleUser()->state(['is_enabled' => true])->create();
        $user      = User::factory()->enabled()->create();
        $locations = Location::factory()->count(3)->create();

        $choiceData = [
            'user_id'           => $user->getKey(),
            'selectedLocations' => [
                $locations[0]->getKey(),
                $locations[2]->getKey(),
            ],
        ];

        $this->actingAs($admin)
            ->putJson("/user/available-locations", $choiceData)
            ->assertInvalid(['featureDisabled']);
    }

    public function test_get_admin_users_only_returns_users_with_admin_role(): void
    {
        $admins = User::factory()->count(3)->adminRoleUser()->create();
        User::factory()->enabled()->count(3)->create();

        $this->actingAs($admins[0])
            ->getJson("/admin/admin-users")
            ->assertOk()
            ->assertJsonCount(3)
            ->assertJson([
                ['id' => $admins[0]->id, 'name' => $admins[0]->name],
                ['id' => $admins[1]->id, 'name' => $admins[1]->name],
                ['id' => $admins[2]->id, 'name' => $admins[2]->name],
            ]);
    }

    public function test_does_attach_spouse(): void
    {
        $admin = User::factory()->enabled()->adminRoleUser()->create();
        $users = User::factory()
            ->enabled()
            ->sequence(['gender' => 'male'], ['gender' => 'female'])
            ->count(2)
            ->create();

        $userData              = $users[0]->makeVisible(['role', 'mobile_phone'])->toArray();
        $userData['spouse_id'] = $users[1]->getKey();

        $this->actingAs($admin)
            ->putJson("/admin/users/{$users[0]->getKey()}", $userData)
            ->assertRedirect();

        $users = $users->fresh('spouse');

        $this->assertSame($users[0]->spouse_id, $users[1]->getKey());
        $this->assertSame($users[0]->spouse->getKey(), $users[1]->getKey());

        $this->assertSame($users[1]->spouse_id, $users[0]->getKey());
        $this->assertSame($users[1]->spouse->getKey(), $users[0]->getKey());
    }

    public function test_does_detach_spouse(): void
    {
        $admin  = User::factory()->enabled()->adminRoleUser()->create();
        $male   = User::factory()->enabled()->male()->create();
        $female = User::factory()->enabled()->female()->create();

        $male->update(['spouse_id' => $female->getKey()]);
        $female = $female->fresh();

        $this->assertSame($male->spouse_id, $female->id);
        $this->assertSame($female->spouse_id, $male->id);

        $maleData = $male->makeVisible(['role', 'mobile_phone'])->toArray();

        $this->actingAs($admin)
            ->putJson("/admin/users/{$male->getKey()}", $maleData)
            ->assertRedirect();

        $male   = $male->fresh('spouse');
        $female = $female->fresh('spouse');

        $this->assertNull($male->spouse_id);
        $this->assertNull($male->spouse);

        $this->assertNull($female->spouse_id);
        $this->assertNull($female->spouse);
    }

    public function test_cannot_attach_user_who_is_already_a_spouse(): void
    {
        $admin  = User::factory()->enabled()->adminRoleUser()->create();
        $male   = User::factory()->enabled()->male()->create();
        $male2  = User::factory()->enabled()->male()->create();
        $female = User::factory()->enabled()->female()->create();

        $male->update(['spouse_id' => $female->getKey()]);
        $female = $female->fresh();

        $this->assertSame($male->spouse_id, $female->id);
        $this->assertSame($female->spouse_id, $male->id);
        $this->assertNull($male2->spouse_id);

        $male2Data              = $male2->makeVisible(['role', 'mobile_phone'])->toArray();
        $male2Data['spouse_id'] = $female->getKey();

        $this->actingAs($admin)
            ->putJson("/admin/users/{$male2->getKey()}", $male2Data)
            ->assertInvalid(['spouse_id' => "The 'spouse' has already been attached to another user"]);
    }

    public function test_can_only_attach_user_of_opposite_gender(): void
    {
        $admin = User::factory()->enabled()->adminRoleUser()->create();
        $male  = User::factory()->enabled()->male()->create();
        $male2 = User::factory()->enabled()->male()->create();

        $userData              = $male->makeVisible(['role', 'mobile_phone'])->toArray();
        $userData['spouse_id'] = $male2->getKey();

        $this->actingAs($admin)
            ->putJson("/admin/users/{$male->getKey()}", $userData)
            ->assertInvalid(['spouse_id' => 'The spouse id needs a user who is not male']);
    }
}
