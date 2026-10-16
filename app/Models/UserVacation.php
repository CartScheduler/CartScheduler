<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class UserVacation extends Model
{
    use HasFactory, Prunable;

    public function prunable(): Builder
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return static::where('end_date', '<', now()->subMonths(3));
    }

    protected function startDate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value)->format('Y-m-d'),
            set: fn ($value) => $this->attributes['start_date'] = Carbon::parse($value)->startOfDay()
        );
    }

    protected function endDate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value)->format('Y-m-d'),
            set: fn ($value) => $this->attributes['end_date'] = Carbon::parse($value)->endOfDay()
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
