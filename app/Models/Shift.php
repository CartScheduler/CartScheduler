<?php

namespace App\Models;

use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @method ShiftFactory factory()
 */
class Shift extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'name',
        'location_id',
        'day_monday',
        'day_tuesday',
        'day_wednesday',
        'day_thursday',
        'day_friday',
        'day_saturday',
        'day_sunday',
        'start_time',
        'end_time',
        'available_from',
        'available_to',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'day_monday' => 'boolean',
            'day_tuesday' => 'boolean',
            'day_wednesday' => 'boolean',
            'day_thursday' => 'boolean',
            'day_friday' => 'boolean',
            'day_saturday' => 'boolean',
            'day_sunday' => 'boolean',
            'is_enabled' => 'boolean',
            'available_from' => 'datetime',
            'available_to' => 'datetime',
        ];
    }

    /** @noinspection PhpUnused */
    protected function availableFrom(): Attribute
    {
        return Attribute::make(
            get: static fn (?string $value) => $value ? Carbon::parse($value)->format('Y-m-d') : null,
            set: static fn (?string $value) => $value ? Carbon::parse($value)->startOfDay() : null,
        );
    }

    /** @noinspection PhpUnused */
    protected function availableTo(): Attribute
    {
        return Attribute::make(
            get: static fn (?string $value) => $value ? Carbon::parse($value)->format('Y-m-d') : null,
            set: static fn (?string $value) => $value ? Carbon::parse($value)->endOfDay() : null,
        );
    }

    /** @noinspection PhpUnused */
    protected function startHour(): Attribute
    {
        return Attribute::make(
            get: static fn (?string $value, array $attributes) => Str::of($attributes['start_time'])->before(':')->toInteger(),
        );
    }

    /** @noinspection PhpUnused */
    protected function endHour(): Attribute
    {
        return Attribute::make(
            get: static fn (?string $value, array $attributes) => Str::of($attributes['end_time'])->before(':')->toInteger(),
        );
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->using(ShiftUser::class)->withPivot('id', 'shift_date');
    }

    public function getUsersOnDate(Carbon|string $date): BelongsToMany
    {
        return $this->users()->wherePivot('shift_date', is_string($date) ? $date : $date->toDateString());
    }

    /**
     * @param  Collection<int, User|int>  $users
     */
    public function attachUsersOnDate(Collection $users, Carbon|string $date): Collection
    {
        $isValid = $users->where(fn ($user) => is_numeric($user->id) || $user instanceof User)->count();
        if ($isValid > 0 && $isValid !== $users->count()) {
            throw new InvalidArgumentException('The collection must contain only numeric IDs or persisted User instances');
        }

        return $users->each(fn ($user) => $this->users()->attach($user, ['shift_date' => $date]));
    }

    public function attachUserOnDate(User|int $user, Carbon|string $date): void
    {
        $this->users()->attach($user, ['shift_date' => $date]);
    }

    public function detachUserOnDate(User|int $user, Carbon|string $date): int
    {
        return $this->getUsersOnDate($date)->detach($user);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }
}
