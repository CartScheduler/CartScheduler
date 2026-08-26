<?php

namespace App\Http\Requests;

use App\Actions\SanitiseRichText;
use Illuminate\Foundation\Http\FormRequest;

class CreateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Same reasoning as UpdateLocationRequest: sanitise on write so the
        // stored value is the safe one.
        $this->merge([
            'description' => app(SanitiseRichText::class)->execute($this->get('description')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxVolunteers = config('cart-scheduler.max_volunteers_per_location');

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:4000000000'],
            'min_volunteers' => ['required', 'integer', 'min:0'],
            'max_volunteers' => ['required', 'integer', "max:$maxVolunteers"],
            'requires_brother' => ['boolean'],
            'latitude' => ['numeric', 'min:-90', 'max:90.999999999999'],
            'longitude' => ['numeric', 'min:-180', 'max:180.999999999999'],
            'is_enabled' => ['boolean'],
            'shifts' => ['array'],
            'shifts.*.id' => ['filled', 'integer'],
            'shifts.*.day_monday' => ['boolean'],
            'shifts.*.day_tuesday' => ['boolean'],
            'shifts.*.day_wednesday' => ['boolean'],
            'shifts.*.day_thursday' => ['boolean'],
            'shifts.*.day_friday' => ['boolean'],
            'shifts.*.day_saturday' => ['boolean'],
            'shifts.*.day_sunday' => ['boolean'],
            'shifts.*.start_time' => ['required', 'date_format:H:i:s'],
            'shifts.*.end_time' => ['required', 'date_format:H:i:s'],
            'shifts.*.available_from' => ['nullable', 'date', 'before_or_equal:shifts.*.available_to'],
            'shifts.*.available_to' => ['nullable', 'date', 'after_or_equal:shifts.*.available_from'],
            'shifts.*.is_enabled' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        $formatMsg = 'Please use the format 04xx xxx xxx';

        return [
            'mobile_phone.regex' => "The mobile phone can contain only numbers and spaces. $formatMsg",
            'mobile_phone.min' => $formatMsg,
            'mobile_phone.max' => $formatMsg,
            'shifts.*.start_time.date_format' => 'Please use the format HH:mm:ss',
            'shifts.*.end_time.date_format' => 'Please use the format HH:mm:ss',
            'shifts.*.available_from' => "The 'available from' date must be a valid date and time and needs to be before the 'available to' date",
            'shifts.*.available_to' => "The 'available to' date must be a valid date and time and needs to be after the 'available from' date",
        ];
    }
}
