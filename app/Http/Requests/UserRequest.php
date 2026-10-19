<?php

namespace App\Http\Requests;

use App\Actions\GetUserValidationUtils;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @throws BindingResolutionException
     */
    public function prepareForValidation(): void
    {
        $validationUtils = app()->make(GetUserValidationUtils::class);

        $preparedData = $validationUtils->prepare($this->all());

        // A request without a spouse must leave the existing link alone, rather than detaching it
        if (! $this->exists('spouse_id') && $preparedData['spouse_id'] === null) {
            unset($preparedData['spouse_id']);
        }

        $this->merge($preparedData);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $validationUtils = app()->make(GetUserValidationUtils::class);
        $rules = $validationUtils->rules();

        $rules['email'][] = Rule::unique('users')->ignore($this->get('id'));
        $rules['is_enabled'][] = ['boolean'];

        // Resubmitting the spouse already attached to this user is not a conflict
        $rules['spouse_id'] = array_map(
            fn (mixed $rule) => $rule === 'unique:users,spouse_id'
                ? Rule::unique('users', 'spouse_id')->ignore($this->get('id'))
                : $rule,
            $rules['spouse_id'],
        );

        return $rules;
    }

    public function after(): array
    {
        return [
            (app()->make(GetUserValidationUtils::class))->extraValidation($this->isPrecognitive()),
        ];
    }

    public function messages(): array
    {
        $formatMsg = 'Please use the format 04xx xxx xxx';

        return [
            'mobile_phone.regex' => "The mobile phone can contain only numbers and spaces. $formatMsg",
            'mobile_phone.min' => $formatMsg,
            'mobile_phone.max' => $formatMsg,
            'spouse_id.unique' => "The 'spouse' has already been attached to another user",
        ];
    }
}
