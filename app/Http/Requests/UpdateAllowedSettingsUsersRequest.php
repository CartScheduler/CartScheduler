<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAllowedSettingsUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'allowedSettingsUsers' => ['required', 'array', 'min:1'],
            //            'allowedSettingsUsers.*' => ['required', 'integer', Rule::exists('users')->where(
            //                fn(Builder $query) => $query->where('role', 'admin')
            //                    ->where('is_enabled', true)
            //            )],
        ];
    }

    public function messages(): array
    {
        return [
            'siteName.required' => 'Site name is required',
        ];
    }
}
