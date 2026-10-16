<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Tags\HasTags;

class Report extends Model
{
    use HasFactory;
    use HasTags;
    use LogsActivity;

    protected $fillable = [
        'shift_id',
        'report_submitted_user_id',
        'shift_date',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'report_submitted_user_id');
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
            'shift_date' => 'custom_datetime:Y-m-d',
            'metadata' => 'array',
        ];
    }
}
