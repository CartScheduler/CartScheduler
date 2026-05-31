<?php

namespace App\Http\Resources;

use App\Models\ShiftUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShiftUser
 */
class ExportShiftAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'mobile_phone' => $this->user->mobile_phone,
            'shift_date' => $this->shift_date,
            'start_time' => $this->shift->start_time,
            'end_time' => $this->shift->end_time,
            'location' => $this->shift->location->name,
        ];
    }
}
