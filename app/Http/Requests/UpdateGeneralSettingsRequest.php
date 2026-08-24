<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGeneralSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'siteName' => ['required', 'string', 'max:255'],
            // TODO: add test for lt and gt rules
            'systemShiftStartHour' => ['required', 'integer', 'min:0', 'max:23', 'lt:systemShiftEndHour'],
            'systemShiftEndHour' => ['required', 'integer', 'min:0', 'max:23', 'gt:systemShiftStartHour'],
            'enableShiftRemoveConfirm' => ['required', 'boolean'],
            'shiftRemoveConfirmMessage' => [
                Rule::requiredIf($this->boolean('enableShiftRemoveConfirm')),
                'nullable',
                'string',
                'max:200',
            ],
        ];
    }

    public function messages(): array
    {
        $start = $this->converTimeTo12Hour('systemShiftEndHour');
        $end = $this->converTimeTo12Hour('systemShiftStartHour');

        return [
            'siteName.required' => 'Site name is required',
            'systemShiftStartHour.lt' => "Start hour must be before $start",
            'systemShiftEndHour.gt' => "End hour must be after $end",
        ];
    }

    protected function converTimeTo12Hour(string $key): string
    {
        $value = $this->input($key);
        if ($value < 1) {
            return '12am';
        }
        if ($value > 12) {
            return $value - 12 . 'pm';
        }
        return "{$value}am";
    }
}
