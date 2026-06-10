<?php

namespace Tests\Feature\App;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class SetUserPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_can_see_set_password_page(): void
    {
        $user  = User::factory()->enabled()->state(['password' => null])->create();
        $token = Password::createToken($user);

        $this->get("/set-password/$user->id/$token")
            ->assertInertia(fn(AssertableInertia $page) => $page
                ->component('Profile/SetPassword')
                ->has('editUser', fn(AssertableInertia $data) => $data
                    ->where('email', $user->email)
                    ->where('name', $user->name)
                )
                ->where('token', $token)
                ->where('siteName', config('app.name'))
            )
            ->assertOk();
    }

    public function test_user_with_set_password_is_directed_to_login_page(): void
    {
        $user = User::factory()->enabled()->state(['password' => 'password'])->create();

        $this->get("/set-password/$user->id/mock-text")
            ->assertRedirect('/login');
    }

    public function test_logged_in_user_is_directed_to_home_page(): void
    {
        $user = User::factory()->enabled()->state(['password' => 'password'])->create();

        $this->actingAs($user)
            ->get("/set-password/$user->id/mock-text")
            ->assertRedirect('/');
    }

    public function test_visitor_has_invalid_hashed_email(): void
    {
        $user = User::factory()->enabled()->state(['password' => null])->create();

        // So we don't see the exception in the logs...
        $this->withoutExceptionHandling([NotFoundHttpException::class])
            ->getJson("/set-password/$user->id/" . base64_encode('mock-text'))
            ->assertNotFound();
    }

    public function test_visitor_can_set_password(): void
    {
        $user  = User::factory()->enabled()->state(['password' => null])->create();
        $token = Password::createToken($user);

        $this->post("/set-password", [
            'password_confirmation' => 'password',
            'password'              => 'password',
            'token'                 => $token,
            'email'                 => $user->email,
        ])
            ->assertRedirect('/login')
            ->assertSessionHas('flash.setPassword', "Your password has been set. Please use it to log in.");

        $user->refresh();
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_set_password_validation_is_working(): void
    {
        $this->post("/set-password", [
            'password_confirmation' => 'abc123',
            'password'              => 'password',
        ])
            ->assertRedirect()
            ->assertInvalid([
                'password',
                'token' => config('cart-scheduler.set_password_generic_error_message') . '(100)',
                'email' => config('cart-scheduler.set_password_generic_error_message') . '(200)',
            ]);
    }

    public function test_invalid_hashed_email_fails_properly(): void
    {
        $user = User::factory()->enabled()->state(['password' => null])->create();

        // So we don't see the exception in the logs...
        $this->withoutExceptionHandling([NotFoundHttpException::class])
            ->post("/set-password", [
                'password_confirmation' => 'password',
                'password'              => 'password',
                'token'                 => base64_encode('mock-text'),
                'email'                 => $user->email,
            ])
            ->assertNotFound();
    }
}
