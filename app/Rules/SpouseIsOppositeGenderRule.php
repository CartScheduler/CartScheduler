<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class SpouseIsOppositeGenderRule implements DataAwareRule, ValidationRule
{
    protected array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_numeric($value)) {
            $fail("The :attribute should an integer");
        }

        $spouse = User::find((int) $value);
        if (!$spouse) {
            $fail("The :attribute does not exist in the database");
        }

        $user = User::find((int) $this->data['id']);

        if (!$user) {
            $fail('The :attribute needs a user id to be sent with it');
        }

        if (
            (strtolower($user->gender) === 'male' && $spouse->gender === 'female') ||
            (strtolower($user->gender) === 'female' && $spouse->gender === 'male')
        ) {
            return;
        }
        $fail("The :attribute needs a user who is not $spouse->gender");
    }
}
