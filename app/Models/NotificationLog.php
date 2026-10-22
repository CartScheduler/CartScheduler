<?php

namespace App\Models;

use App\Enums\NotificationStatus;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;

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
     * Rows whose email was never sent successfully afterwards.
     *
     * @param  Builder<NotificationLog>  $query
     */
    public function scopeUnresolved(Builder $query): void
    {
        $query->whereNotExists($this->sentLater());
    }

    /**
     * Rows whose email was sent successfully afterwards, for example by "Send again".
     *
     * @param  Builder<NotificationLog>  $query
     */
    public function scopeResolved(Builder $query): void
    {
        $query->whereExists($this->sentLater());
    }

    /**
     * A later, successful send to the same person: same type and, for emails about assignments, at
     * least one of the same assignments. Matches GetLaterNotificationAttempts.
     *
     * @return Closure(QueryBuilder): QueryBuilder
     */
    private function sentLater(): Closure
    {
        $table = $this->getTable();

        return fn (QueryBuilder $later) => $later
            ->from("$table as later")
            ->whereColumn('later.type', "$table.type")
            ->whereColumn('later.notifiable_type', "$table.notifiable_type")
            ->whereColumn('later.notifiable_id', "$table.notifiable_id")
            ->whereColumn('later.id', '>', "$table.id")
            ->where('later.status', NotificationStatus::Sent->value)
            ->where(fn (QueryBuilder $subjects) => $subjects
                ->whereNotExists(fn (QueryBuilder $own) => $own
                    ->from('notification_log_subjects as own')
                    ->whereColumn('own.notification_log_id', "$table.id"))
                ->orWhereExists(fn (QueryBuilder $shared) => $shared
                    ->from('notification_log_subjects as own')
                    ->join('notification_log_subjects as theirs', fn (JoinClause $join) => $join
                        ->on('theirs.subject_type', '=', 'own.subject_type')
                        ->on('theirs.subject_id', '=', 'own.subject_id'))
                    ->whereColumn('own.notification_log_id', "$table.id")
                    ->whereColumn('theirs.notification_log_id', 'later.id')));
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
