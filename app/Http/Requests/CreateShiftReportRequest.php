<?php

namespace App\Http\Requests;

use App\Models\Report;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class CreateShiftReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'shift_date' => ['required', 'date', 'date_format:Y-m-d', 'before_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i:s'],
            'shift_was_cancelled' => ['boolean'],
            'placements_count' => ['integer', 'min:0'],
            'videos_count' => ['integer', 'min:0'],
            'requests_count' => ['integer', 'min:0'],
            'comments' => ['string', 'max:500'],
            'tags' => ['array'],
            'tags.*' => ['required', 'integer', 'exists:tags,id'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (Report::where('shift_id', $this->integer('shift_id'))->where('shift_date', $this->date('shift_date'))->exists()) {
                    $validator->errors()->add('shift_id', 'A report already exists for this shift');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'start_time' => 'The start time needs to be in the format h:m:s',
            'shift_was_cancelled.boolean' => 'Shift was cancelled must be a boolean',
            'placements_count.integer' => 'Placements count must be a whole number',
            'videos_count.integer' => 'Videos count must be a whole number',
            'requests_count.integer' => 'Requests count must be a whole number',
            'placements_count.min' => 'Placements count cannot be negative',
            'videos_count.min' => 'Videos count cannot be negative',
            'requests_count.min' => 'Requests count cannot be negative',
            'comments.string' => 'Comments must be a string',
            'comments.max' => 'Comments must be less than 500 characters',
        ];
    }
}
