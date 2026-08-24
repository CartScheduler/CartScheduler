<?php

namespace App\Data;

use App\Models\UserAvailability;
use Spatie\LaravelData\Data;

class ExportUserAvailabilityData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $day_monday,
        public ?string $day_tuesday,
        public ?string $day_wednesday,
        public ?string $day_thursday,
        public ?string $day_friday,
        public ?string $day_saturday,
        public ?string $day_sunday,
        public int $num_mondays,
        public int $num_tuesdays,
        public int $num_wednesdays,
        public int $num_thursdays,
        public int $num_fridays,
        public int $num_saturdays,
        public int $num_sundays,
        public ?string $comments,
    ) {
    }

    public static function fromUserAvailability(UserAvailability $availability): self
    {
        return new self(
            id: $availability->user->id,
            name: $availability->user->name,
            day_monday: self::rawDay($availability, 'day_monday'),
            day_tuesday: self::rawDay($availability, 'day_tuesday'),
            day_wednesday: self::rawDay($availability, 'day_wednesday'),
            day_thursday: self::rawDay($availability, 'day_thursday'),
            day_friday: self::rawDay($availability, 'day_friday'),
            day_saturday: self::rawDay($availability, 'day_saturday'),
            day_sunday: self::rawDay($availability, 'day_sunday'),
            num_mondays: $availability->num_mondays,
            num_tuesdays: $availability->num_tuesdays,
            num_wednesdays: $availability->num_wednesdays,
            num_thursdays: $availability->num_thursdays,
            num_fridays: $availability->num_fridays,
            num_saturdays: $availability->num_saturdays,
            num_sundays: $availability->num_sundays,
            comments: $availability->comments,
        );
    }

    private static function rawDay(UserAvailability $availability, string $attribute): ?string
    {
        $value = $availability->getAttributes()[$attribute] ?? null;

        return $value === null ? null : (string) $value;
    }
}
