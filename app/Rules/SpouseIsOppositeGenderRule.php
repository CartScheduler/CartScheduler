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
            return;
        }

        $spouse = User::find((int) $value);
        if (!$spouse) {
            $fail("The :attribute does not exist in the database");
            return;
        }

        // Prefer the submitted gender, so a new user, or one whose gender is changing, is checked correctly
        $gender = $this->data['gender'] ?? (isset($this->data['id']) ? User::find((int) $this->data['id'])?->gender : null);

        if (!$gender) {
            $fail('The :attribute needs a user gender to be sent with it');
            return;
        }

        if (
            (strtolower($gender) === 'male' && $spouse->gender === 'female') ||
            (strtolower($gender) === 'female' && $spouse->gender === 'male')
        ) {
            return;
        }
        $fail("The :attribute needs a user who is not $spouse->gender");
    }
}
