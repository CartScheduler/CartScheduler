<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Str;

class CreateUserCommand extends Command
{
    protected $signature = 'cart-scheduler:create-user
                                {name : Full name wrapped in quotes}
                                {email : Valid email address}
                                {phone : Mobile phone number}
                                {gender : Allowed options are: male, m, female, f}
                                {password? : If not set here, the user will be sent an email prompting for their password}';

    protected $description = 'Create the default user. This user will have admin access.';

    public function handle(): void
    {
        if (User::count() > 0) {
            $this->error('Only one user can be created from this interface');

            return;
        }

        $user = new User;
        $user->uuid = Str::uuid()->toString();
        $user->name = $this->argument('name');
        $user->email = $this->argument('email');
        $user->password = $this->argument('password') ? bcrypt($this->argument('password')) : null;
        $user->mobile_phone = $this->argument('phone');
        $user->gender = ($this->argument('gender') === 'male' || $this->argument('gender') === 'm') ? 'male' : 'female';
        $user->role = 'admin';
        if ($this->argument('password')) {
            $user->saveQuietly();
        } else {
            $user->save();
        }

        $this->info("User $user->name created successfully");
    }
}
