<?php

namespace App\Models;

use App\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * One row per notification per channel (an email, and later a push notification).
 */
class NotificationLog extends Model
{
    use HasFactory, Prunable;

    /**
     * A row still "sending" after this many minutes stopped part-way, e.g. the mail server
     * connection failed while the person waited, so it is reported as a failure.
     */
    public const int STALLED_AFTER_MINUTES = 15;

    protected $fillable = [
        'notification_id',
        'channel',
        'type',
        'notifiable_type',
        'notifiable_id',
        'recipient',
        'subject',
        'status',
        'attempts',
        'error',
        'queued_at',
        'sent_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => NotificationStatus::class,
            'attempts' => 'integer',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function shiftUsers(): MorphToMany
    {
        return $this->morphedByMany(ShiftUser::class, 'subject', 'notification_log_subjects');
    }

    public function isStalled(): bool
    {
        return $this->status === NotificationStatus::Sending
            && $this->updated_at?->lt(now()->subMinutes(self::STALLED_AFTER_MINUTES));
    }

    /**
     * Failed rows, plus rows that stopped part-way through sending.
     *
     * @param  Builder<NotificationLog>  $query
     */
    public function scopeFailedOrStalled(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('status', NotificationStatus::Failed)
            ->orWhere(fn (Builder $query) => $query
                ->where('status', NotificationStatus::Sending)
                ->where('updated_at', '<', now()->subMinutes(self::STALLED_AFTER_MINUTES))));
    }

    /**
     * Keeps rows linked to shifts that haven't happened yet, because they stop volunteers being
     * emailed twice about the same assignment.
     *
     * @return Builder<NotificationLog>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->where('created_at', '<', now()->subDays(config('cart-scheduler.notification_log_retention_days')))
            ->whereDoesntHave('shiftUsers', fn (Builder $query) => $query
                ->whereDate('shift_date', '>=', now()->toDateString()));
    }
}
