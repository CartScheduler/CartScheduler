<?php

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @method LocationFactory factory()
 */
class Location extends Model
{
    use HasFactory;
    use LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'name',
        'description',
        'min_volunteers',
        'max_volunteers',
        'requires_brother',
        'latitude',
        'longitude',
        'is_enabled',
        'sort_order',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::addGlobalScope('sort_order', static fn (Builder $builder) => $builder
            ->orderBy('sort_order')
            ->orderBy('name')
        );
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    protected function casts(): array
    {
        return [
            'min_volunteers' => 'integer',
            'max_volunteers' => 'integer',
            'requires_brother' => 'boolean',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'is_enabled' => 'boolean',
        ];
    }
}
