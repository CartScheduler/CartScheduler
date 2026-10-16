<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::unsetEventDispatcher(); // stops the UserAccountCreated mail from being sent
        User::factory()->devUser()->create();
        User::factory()->testUser()->create();
        User::factory()->adminRoleUser()->count(3)->create();
        $husband = User::factory()->create([
            'name' => 'Husband',
            'gender' => 'male',
            'marital_status' => 'married',
        ]);
        $wife = User::factory()->create([
            'name' => 'Wife',
            'gender' => 'female',
            'marital_status' => 'married',
            'spouse_id' => $husband->id,
        ]);
        $husband->spouse_id = $wife->id;
        $husband->save();
        User::factory()->userRoleUser()->count(50)->create();
    }
}
