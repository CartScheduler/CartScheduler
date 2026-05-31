<?php

namespace App\Http\Resources;

use App\Models\UserAvailability;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserAvailability
 */
class ExportUserAvailabilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->user->id,
            'name' => $this->user->name,
            'day_monday' => $this->rawDay('day_monday'),
            'day_tuesday' => $this->rawDay('day_tuesday'),
            'day_wednesday' => $this->rawDay('day_wednesday'),
            'day_thursday' => $this->rawDay('day_thursday'),
            'day_friday' => $this->rawDay('day_friday'),
            'day_saturday' => $this->rawDay('day_saturday'),
            'day_sunday' => $this->rawDay('day_sunday'),
            'num_mondays' => $this->num_mondays,
            'num_tuesdays' => $this->num_tuesdays,
            'num_wednesdays' => $this->num_wednesdays,
            'num_thursdays' => $this->num_thursdays,
            'num_fridays' => $this->num_fridays,
            'num_saturdays' => $this->num_saturdays,
            'num_sundays' => $this->num_sundays,
            'comments' => $this->comments,
        ];
    }

    private function rawDay(string $attribute): ?string
    {
        $value = $this->getAttributes()[$attribute] ?? null;

        return $value === null ? null : (string) $value;
    }
}
