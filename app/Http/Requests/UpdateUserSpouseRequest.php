<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\SpouseIsOppositeGenderRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserSpouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The gender rule reads the user being edited from the payload, which here is the route.
     */
    public function prepareForValidation(): void
    {
        $this->merge(['id' => $this->routeUser()?->getKey()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'spouse_id' => [
                'present',
                'nullable',
                'integer',
                'exists:users,id',
                Rule::unique('users', 'spouse_id')->ignore($this->routeUser()?->getKey()),
                new SpouseIsOppositeGenderRule,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'spouse_id.unique' => "The 'spouse' has already been attached to another user",
        ];
    }

    private function routeUser(): ?User
    {
        $user = $this->route('user');

        return $user instanceof User ? $user : null;
    }
}
