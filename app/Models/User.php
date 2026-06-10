<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Traits\User\AttachesSpouse;
use App\Models\Traits\User\RevokesUserAccess;
use App\Models\Traits\User\SendsWelcomeEmail;
use App\Settings\GeneralSettings;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\CausesActivity;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @method UserFactory factory()
 */
class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use LogsActivity;
    use CausesActivity;
    use SendsWelcomeEmail;
    use AttachesSpouse;
    use RevokesUserAccess;

    protected function casts(): array
    {
        return [
            'is_enabled'          => 'boolean',
            'is_unrestricted'     => 'boolean',
            'responsible_brother' => 'boolean',
            'email_verified_at'   => 'datetime',
            'year_of_birth'       => 'integer',
            'password'            => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::created(static fn(self $user) => self::sendWelcomeEmail($user));
        static::saved(static fn(self $user) => self::attachSpouse($user));
        static::updating(static fn(self $user) => self::detachSpouse($user));
        static::updated(static fn(self $user) => self::revokePrivilegedAccess($user));
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uuid',
        'name',
        'email',
        'gender',
        'is_enabled',
        'is_unrestricted',
        'mobile_phone',
        'year_of_birth',
        'marital_status',
        'spouse_id',
        'appointment',
        'serving_as',
        'responsible_brother',
        'is_unrestricted',
        'password',
        'role',
    ];
    /**
     * The only attributes that should be visible for serialization when calling toArray().
     */
    protected $visible = [
        'id',
        'uuid',
        'name',
        'email',
        'gender',
    ];
    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'has_logged_in', // Used to determine if the user has logged in before by checking the password field
    ];

    /** @noinspection PhpUnused */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    /** @noinspection PhpUnused */
    public function scopeShiftsOnDate(Builder $query, Carbon $date): Builder
    {
        return $query->with([
            'shifts' => fn(BelongsToMany $query) => $query
                ->wherePivot('shift_date', '=', $date)
        ]);
    }

    /**
     * Format the phone number
     *
     * @noinspection PhpUnused
     */
    protected function mobilePhone(): Attribute
    {
        // todo https://github.com/Propaganistas/Laravel-Phone
        return Attribute::make(
            get: static function ($value) {
                if (preg_match('/^(\d{4})(\d{3})(\d{3})$/', $value, $matches)) {
                    $result = $matches[1] . ' ' . $matches[2] . ' ' . $matches[3];
                } else {
                    $result = $value;
                }

                return $result;
            },
            set: static function ($value) {
                $value = preg_replace('/\D+/', '', $value); // remove all non-digits

                return preg_replace('/^614/', '04', $value); // If the number starts with 614, replace with 04
            },
        );
    }

    /**
     * @noinspection PhpUnused
     */
    protected function hasLoggedIn(): Attribute
    {
        return Attribute::make(
            get: static fn($value, $attributes) => isset($attributes['password']) && $attributes['password'],
        );
    }

    protected function isRestricted(): Attribute
    {
        return Attribute::make(
            get: static fn(
                $value,
                $attributes
            ) => isset($attributes['is_unrestricted']) && !$attributes['is_unrestricted'],
        );
    }

    public function shifts(): BelongsToMany
    {
        return $this->belongsToMany(Shift::class)->using(ShiftUser::class)->withPivot('id', 'shift_date');
    }

    public function getShiftsOnDate(Carbon|string $date): BelongsToMany
    {
        return $this->shifts()->wherePivot('shift_date', is_string($date) ? $date : $date->toDateString());
    }

    public function attachShiftOnDate(Shift|int $shift, Carbon|string $date): void
    {
        $this->shifts()->attach($shift, ['shift_date' => $date]);
    }

    public function detachShiftOnDate(Shift|int $shift, Carbon|string $date): int
    {
        return $this->getShiftsOnDate($date)->detach($shift);
    }

    public function spouse(): HasOne
    {
        return $this->hasOne(__CLASS__, 'id', 'spouse_id');
    }

    public function bookings(): HasMany
    {
        return $this->HasMany(ShiftUser::class);
    }

    public function availability(): HasOne
    {
        return $this->hasOne(UserAvailability::class);
    }

    public function vacations(): HasMany
    {
        return $this->hasMany(UserVacation::class);
    }

    public function rosterLocations(): BelongsToMany
    {
        return $this->belongsToMany(related: Location::class, table: 'user_roster_locations');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['password', 'remember_token', 'two_factor_recovery_codes', 'two_factor_secret'])
            ->logOnlyDirty();
    }
}
