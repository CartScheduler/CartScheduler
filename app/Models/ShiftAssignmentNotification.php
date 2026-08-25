<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftAssignmentNotification extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'shift_user_id',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function shiftUser(): BelongsTo
    {
        return $this->belongsTo(ShiftUser::class, 'shift_user_id');
    }
}
