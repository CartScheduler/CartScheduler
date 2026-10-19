<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ShiftOverlapsOtherShiftRule implements DataAwareRule, ValidationRule
{
    private array $data;

    private array $shiftTimeBounds;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $this->setupShiftDateBounds(Carbon::parse($this->data['date']));
        $result = DB::table('shifts')
            ->select('id')
            ->where('id', $this->data['shift_id'])
            ->whereBetween('start_time', $this->shiftTimeBounds)
            ->first();
        if (! $result) {
            $fail('Shift overlaps with another shift.');
        }
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    private function setupShiftDateBounds(Carbon $date): void
    {
        $oldStartTime = DB::table('shifts')
            ->where('id', $this->data['old_shift_id'])
            ->first('start_time')->start_time;

        $startTime = $date->toImmutable()->setTimeFromTimeString($oldStartTime);

        $this->shiftTimeBounds = [
            $startTime->sub(30, 'minutes')->format('H:i:s'),
            $startTime->add(30, 'minutes')->format('H:i:s'),
        ];
    }
}
