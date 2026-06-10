<?php /** @noinspection ALL */

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password = null;

    /**
     * This factory does not need to send emails whenever a new user has been created so instead of updating the entire
     * codebase, I'm just overriding the create method
     */
    public function create($attributes = [], ?Model $parent = null)
    {
        return Model::withoutEvents(fn() => parent::create($attributes, $parent));
    }


    /**
     * Define the model's default state.
     *
     * @return array
     * @throws \OverflowException
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(['male', 'female']);
        return [
            'uuid'                => fake()->uuid(),
            'name'                => fake()->name(),
            'email'               => fake()->unique()->safeEmail(),
            'role'                => fake()->randomElement(['admin', 'user']),
            //'email_verified_at'  => now(),
            'gender'              => $gender,
            'mobile_phone'        => fake()->phoneNumber(),
            'year_of_birth'       => fake()->numberBetween(1950, date('Y') - 18),
            'marital_status'      => fake()->randomElement(['married', 'single', 'divorced', 'separated', 'widowed']),
            'appointment'         => $this->getAppointment($gender),
            'serving_as'          => fake()->randomElement([
                'field missionary', 'special pioneer', 'bethel family member', 'regular pioneer', 'publisher'
            ]),
            'responsible_brother' => $gender === 'male' && fake()->boolean(5),
            'is_enabled'          => true,
            'is_unrestricted'     => true,
            'password'            => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token'      => Str::random(10),
        ];
    }

    public function devUser(): self
    {
        return $this->state(fn(array $attributes) => [
            'name'        => 'Admin',
            'email'       => 'admin@example.com',
            'role'        => 'admin',
            'gender'      => 'male',
            'appointment' => 'elder'
        ]);
    }

    public function testUser(): self
    {
        return $this->state(fn(array $attributes) => [
            'name'        => 'User',
            'email'       => 'test@example.com',
            'role'        => 'user',
            'gender'      => 'male',
            'appointment' => 'elder'
        ]);
    }

    public function adminRoleUser(): self
    {
        return $this->state(fn(array $attributes) => [
            'role'        => 'admin',
            'gender'      => 'male',
            'appointment' => 'elder'
        ]);
    }

    public function userRoleUser(): self
    {
        return $this->state(fn(array $attributes) => [
            'role'       => 'user',
            'is_enabled' => random_int(0, 9) !== 9,
        ]);
    }

    public function enabled(): self
    {
        return $this->state(fn(array $attributes) => [
            'role'       => 'user',
            'is_enabled' => true,
        ]);
    }

    public function responsibleBrother(): self
    {
        return $this->state(fn(array $attributes) => [
            'role'                => 'user',
            'responsible_brother' => true,
            'is_enabled'          => true,
        ]);
    }

    public function female(): self
    {
        return $this->state(fn(array $attributes) => [
            'gender' => 'female',
        ]);
    }

    public function male(): self
    {
        return $this->state(fn(array $attributes) => [
            'gender' => 'male',
        ]);
    }

    public function publisher(): self
    {
        return $this->state(fn(array $attributes) => [
            'role'       => 'user',
            'serving_as' => 'publisher',
            'is_enabled' => true,
        ]);
    }

    public function pioneer(): self
    {
        return $this->state(fn(array $attributes) => [
            'role'       => 'user',
            'serving_as' => fake()->randomElement([
                'field missionary', 'special pioneer', 'bethel family member', 'regular pioneer'
            ]),
            'is_enabled' => true,
        ]);
    }

    public function ministerialServant(): self
    {
        return $this->state(fn(array $attributes) => [
            'role'        => 'user',
            'appointment' => 'ministerial servant',
            'gender'      => 'male',
            'is_enabled'  => true,
        ]);
    }

    public function elder(): self
    {
        return $this->state(fn(array $attributes) => [
            'role'        => 'user',
            'appointment' => 'elder',
            'gender'      => 'male',
            'is_enabled'  => true,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return Factory
     */
    public function unverified(): self
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    protected function getAppointment($gender = 'male'): ?string
    {
        if ($gender !== 'male') {
            return null;
        }
        $appointment = fake()->randomElement(['elder', 'ministerial servant', '']);
        if ($appointment === '') {
            return null;
        }
        return $appointment;
    }
}
