<?php

namespace App\Http\Requests;

use App\Actions\GetUserValidationUtils;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function prepareForValidation(): void
    {
        $validationUtils = app()->make(GetUserValidationUtils::class);

        $this->merge(
            $validationUtils->prepare($this->all())
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $validationUtils = app()->make(GetUserValidationUtils::class);
        $rules           = $validationUtils->rules();

        $rules['email'][]      = Rule::unique('users')->ignore($this->get('id'));
        $rules['is_enabled'][] = ['boolean'];

        return $rules;
    }

    public function after(): array
    {
        return [
            (app()->make(GetUserValidationUtils::class))->extraValidation($this->isPrecognitive())
        ];
    }

    public function messages(): array
    {
        $formatMsg = 'Please use the format 04xx xxx xxx';

        return [
            'mobile_phone.regex' => "The mobile phone can contain only numbers and spaces. $formatMsg",
            'mobile_phone.min'   => $formatMsg,
            'mobile_phone.max'   => $formatMsg,
            'spouse_id.unique'   => "The 'spouse' has already been attached to another user",
        ];
    }
}
