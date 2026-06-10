<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Fluent;

class UpdateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Force the location id to be the same as the location id in the route
        $locationId = $this->route('location')->id;
        $shifts     = collect($this->get('shifts', []));
        $shifts     = $shifts->map(function (array $shift) use ($locationId) {
            $shift['location_id'] = $locationId;
            // convert undefined to null so it can update in the database
            $shift['available_from'] = $shift['available_from'] ?? null;
            $shift['available_to'] = $shift['available_to'] ?? null;
            return $shift;
        });
        $this->merge(['shifts' => $shifts->toArray()]);
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
            'name'                    => ['required', 'string', 'max:190'],
            'description'             => ['required', 'string', 'max:4000000000'],
            'min_volunteers'          => ['required', 'integer', 'lte:max_volunteers', 'min:0'],
            'max_volunteers'          => ['required', 'integer', 'gte:min_volunteers', "max:$maxVolunteers"],
            'requires_brother'        => ['boolean'],
            'latitude'                => ['nullable', 'numeric', 'min:-90', 'max:90.999999999999'],
            'longitude'               => ['nullable', 'numeric', 'min:-180', 'max:180.999999999999'],
            'is_enabled'              => ['nullable', 'boolean'],
            'shifts'                  => ['array'],
            'shifts.*.id'             => ['filled', 'integer'],
            'shifts.*.location_id'    => ['required', 'integer'],
            'shifts.*.day_monday'     => ['boolean'],
            'shifts.*.day_tuesday'    => ['boolean'],
            'shifts.*.day_wednesday'  => ['boolean'],
            'shifts.*.day_thursday'   => ['boolean'],
            'shifts.*.day_friday'     => ['boolean'],
            'shifts.*.day_saturday'   => ['boolean'],
            'shifts.*.day_sunday'     => ['boolean'],
            // Note, extra validation is done in withValidator()
            'shifts.*.start_time'     => ['required', 'date_format:H:i:s', 'before_or_equal:shifts.*.end_time'],
            // Note, extra validation is done in withValidator()
            'shifts.*.end_time'       => ['required', 'date_format:H:i:s', 'after_or_equal:shifts.*.start_time'],
            // Note, extra validation is done in withValidator()
            'shifts.*.available_from' => ['nullable', 'date', 'date_format:Y-m-d'],
            // Note, extra validation is done in withValidator()
            'shifts.*.available_to'   => ['nullable', 'date', 'date_format:Y-m-d'],
            'shifts.*.is_enabled'     => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $this->compareAvailableDates($validator);

                // Validate that available_from is before available_to only if the latter is present
                $validator->sometimes(
                    'shifts.*.available_from',
                    'before_or_equal:shifts.*.available_to',
                    fn(Fluent $input, Fluent $shiftData) => (bool)$shiftData->get('available_to'));

                // Validate that available_to is after available_from only if the latter is present
                $validator->sometimes(
                    'shifts.*.available_to',
                    'after_or_equal:shifts.*.available_from',
                    fn(Fluent $input, Fluent $shiftData) => (bool)$shiftData->get('available_from'));
            },
        ];
    }

    public function messages(): array
    {
        $formatMsg = 'Please use the format 04xx xxx xxx';

        return [
            'mobile_phone.regex'                      => "The mobile phone can contain only numbers and spaces. $formatMsg",
            'mobile_phone.min'                        => $formatMsg,
            'mobile_phone.max'                        => $formatMsg,
            'shifts.*.start_time.date_format'         => 'Please use the format HH:mm:ss',
            'shifts.*.end_time.date_format'           => 'Please use the format HH:mm:ss',
            'shifts.*.start_time.before_or_equal'     => "The 'start' time must be before the 'end' time.",
            'shifts.*.end_time.after_or_equal'        => "The 'end' time must be after the 'start' time. ",
            'shifts.*.available_from.date'            => "The 'available from' date must be a valid date and time",
            'shifts.*.available_to.date'              => "The 'available to' date must be a valid date and time",
            'shifts.*.available_from.before_or_equal' => "The 'available from' date must be before or the same as the 'available to' date",
            'shifts.*.available_to.after_or_equal'    => "The 'available to' date must be after or the same the 'available from' date",
        ];
    }

    protected function compareAvailableDates(Validator $validator): void
    {
        $shifts = $this->get('shifts', []);

        foreach ($shifts as $index1 => $shift1) {
            if (!isset($shift1['is_enabled']) || !$shift1['is_enabled']) {
                continue;
            }

            $errorShifts = 0;
            foreach ($shifts as $index2 => $shift2) {
                if (!isset($shift2['is_enabled']) || !$shift2['is_enabled'] || $index1 === $index2) {
                    continue;
                }

                $timesOverlap = $shift1['start_time'] < $shift2['end_time'] && $shift1['end_time'] > $shift2['start_time'];
                // For overlapping periods with available_from and available_to
                $bothSetsOfDates = isset($shift1['available_from'], $shift1['available_to'], $shift2['available_from'], $shift2['available_to']);
                if ($bothSetsOfDates) {
                    $datesOverlap = $shift1['available_from'] < $shift2['available_to'] && $shift1['available_to'] > $shift2['available_from'];
                    if ($datesOverlap && $timesOverlap && $this->doDaysOverlap($shift1, $shift2)) {
                        $errorShifts = 110;
                    }
                }

                // For overlapping periods with only available_from or available_to
                $eitherSetOfDatesShift1 = isset($shift1['available_from']) || isset($shift1['available_to']);
                $noDatesShift2          = !isset($shift2['available_from']) && !isset($shift2['available_to']);
                if ($eitherSetOfDatesShift1 && $noDatesShift2 && $timesOverlap && $this->doDaysOverlap($shift1, $shift2)) {
                    $errorShifts = 120;
                }

                $eitherSetOfDatesShift1 = !isset($shift1['available_from']) && !isset($shift1['available_to']);
                $noDatesShift2          = isset($shift2['available_from']) || isset($shift2['available_to']);
                if ($eitherSetOfDatesShift1 && $noDatesShift2 && $timesOverlap && $this->doDaysOverlap($shift1, $shift2)) {
                    $errorShifts = 121;
                }

                // For shifts with no available_from or available_to
                $noDatesBoth = !isset($shift1['available_from']) && !isset($shift1['available_to']) && !isset($shift2['available_from']) && !isset($shift2['available_to']);
                if ($noDatesBoth && $timesOverlap && $this->doDaysOverlap($shift1, $shift2)) {
                    $errorShifts = 130;
                }

                $startTime = Carbon::now()->setTimeFromTimeString($shift2['start_time'])->format('H:i');
                $endTime   = Carbon::now()->setTimeFromTimeString($shift2['end_time'])->format('H:i');

                if ($errorShifts) {
                    $validator->errors()->add("shifts.$index1.start_time", "There is an active shift that conflicts with this shift between $startTime and $endTime. Only one shift per timeslot can be enabled. [Code: $errorShifts]");
                }
            }
        }
    }

    private array $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    protected function doDaysOverlap($shift1, $shift2): bool
    {
        $daysOverlap = false;
        foreach ($this->days as $day) {
            if ($shift1["day_$day"] && $shift2["day_$day"]) {
                $daysOverlap = true;
                break;
            }
        }
        return $daysOverlap;
    }
}
