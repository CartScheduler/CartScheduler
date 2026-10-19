<?php

namespace App\Http\Requests;

use App\Models\Shift;
use App\Rules\ShiftOverlapsOtherShiftRule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveUserToNewShiftRequest extends FormRequest
{
    protected CarbonImmutable $shiftDate;

    protected ?Shift $oldShift = null;

    protected ?Shift $newShift = null;

    public function shiftDate(): CarbonImmutable
    {
        if (! isset($this->shiftDate)) {
            $this->shiftDate = CarbonImmutable::parse($this->input('date'));
        }

        return $this->shiftDate;
    }

    public function oldShift(): Shift
    {
        if (! isset($this->oldShift)) {
            $this->oldShift = Shift::findOrFail($this->input('old_shift_id'));
        }

        return $this->oldShift;
    }

    public function newShift(): Shift
    {
        if (! isset($this->newShift)) {
            $this->newShift = Shift::findOrFail($this->input('shift_id'));
        }

        return $this->newShift;
    }

    public function rules(): array
    {
        return [
            // todo make shared and reusable rules for checking locations and shifts are valid
            'user_id' => ['required', 'int', 'exists:users,id'],
            'location_id' => [
                'required',
                'int',
                Rule::exists('locations', 'id')->where('is_enabled', true),
            ],
            'date' => ['required', 'date'],
            'shift_id' => [
                'required',
                'int',
                new ShiftOverlapsOtherShiftRule,
                Rule::exists('shifts', 'id')
                    ->where(fn (Builder $query) => $query
                        ->where('is_enabled', true)
                        ->where('location_id', $this->request->getInt('location_id'))
                        ->where(fn (Builder $query) => (match ($this->shiftDate()->dayOfWeekIso) {
                            1 => $query->where('shifts.day_monday', true),
                            2 => $query->where('shifts.day_tuesday', true),
                            3 => $query->where('shifts.day_wednesday', true),
                            4 => $query->where('shifts.day_thursday', true),
                            5 => $query->where('shifts.day_friday', true),
                            6 => $query->where('shifts.day_saturday', true),
                            7 => $query->where('shifts.day_sunday', true),
                        }))
                    ),
            ],
            'old_shift_id' => [
                'required',
                'int',
                Rule::exists('shifts', 'id')
                    ->where(fn (Builder $query) => $query
                        ->where('is_enabled', true)
                        ->whereNot('location_id', $this->request->getInt('location_id'))
                    ),
                'exists:shifts,id',
            ],
        ];
    }
}
