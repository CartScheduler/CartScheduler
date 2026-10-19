<?php

namespace App\Actions;

use App\Models\User;

class GetPrefixedUserName
{
    public function execute(User $user): string
    {
        return ($user->gender === 'male' ? 'Bro' : 'Sis').' '.$user->name;
    }
}
