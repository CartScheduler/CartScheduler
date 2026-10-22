<?php

namespace App\Notifications;

use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Models\NotificationLog;
use App\Notifications\Contracts\HasLogSubjects;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;
use Throwable;

/**
 * Records every notification (one row per channel) and every plain email in `notification_logs`.
 *
 * Sending happens synchronously inside one process: NotificationSending, then the channel sends
 * (firing MessageSending/MessageSent for email), then NotificationSent. The row being sent is kept in
 * `$current` so the email events can fill in its subject, and so a queue exception can be recorded
 * against it.
 */
#[Singleton]
class NotificationLogger
{
    private ?NotificationLog $current = null;

    private ?NotificationLog $currentPlainEmail = null;

    private bool $insideQueuedJob = false;

    public function queued(SendQueuedNotifications $job): void
    {
        foreach ($job->notifiables as $notifiable) {
            foreach ($job->channels ?? (array) $job->notification->via($notifiable) as $channel) {
                $this->record($job->notification, $notifiable, $channel, [
                    'status' => NotificationStatus::Queued,
                    'queued_at' => now(),
                ]);
            }
        }
    }

    /**
     * @return false|null False cancels the send, because this notification was already delivered.
     */
    public function sending(NotificationSending $event): ?bool
    {
        $existing = $this->find($event->notification, $event->channel);

        if ($existing?->status === NotificationStatus::Sent) {
            return false;
        }

        $this->current = $this->record($event->notification, $event->notifiable, $event->channel, [
            'status' => NotificationStatus::Sending,
            'attempts' => ($existing->attempts ?? 0) + 1,
        ]);

        return null;
    }

    public function emailSending(MessageSending $event): void
    {
        $recipient = $this->addresses($event->message->getTo());
        $subject = Str::limit((string) $event->message->getSubject(), 250);

        if ($this->current?->channel === 'mail') {
            $this->current->update(['recipient' => $recipient, 'subject' => $subject]);

            return;
        }

        $this->currentPlainEmail = NotificationLog::create([
            'channel' => 'mail',
            'type' => NotificationType::keyFor($event->data['__laravel_mailable'] ?? 'email'),
            'recipient' => $recipient,
            'subject' => $subject,
            'status' => NotificationStatus::Sending,
            'attempts' => 1,
        ]);
    }

    public function emailSent(MessageSent $event): void
    {
        $this->currentPlainEmail?->update([
            'status' => NotificationStatus::Sent,
            'sent_at' => now(),
        ]);

        $this->currentPlainEmail = null;
    }

    public function sent(NotificationSent $event): void
    {
        $this->find($event->notification, $event->channel)?->update([
            'status' => NotificationStatus::Sent,
            'sent_at' => now(),
            'failed_at' => null,
        ]);

        $this->current = null;
    }

    /**
     * Inside a queued job the queue decides whether this attempt is retried, so only the error is
     * recorded here and `attemptFailed()` sets the status. Outside a job nothing will retry it.
     */
    public function failed(NotificationFailed $event): void
    {
        $exception = $event->data['exception'] ?? null;
        $error = $exception instanceof Throwable
            ? $this->describe($exception)
            : 'The '.$event->channel.' channel reported that sending failed.';

        if ($this->insideQueuedJob) {
            $this->find($event->notification, $event->channel)?->update(['error' => $error]);

            return;
        }

        $this->finish($event->notification, $event->channel, NotificationStatus::Failed, $error);
        $this->current = null;
    }

    /**
     * Records an outcome that isn't a normal send, such as a notification skipped by `shouldSend`.
     */
    public function finish(Notification $notification, string $channel, NotificationStatus $status, ?string $error = null): void
    {
        $this->find($notification, $channel)?->update([
            'status' => $status,
            'error' => $error,
            'failed_at' => $status === NotificationStatus::Failed ? now() : null,
        ]);
    }

    /**
     * An attempt threw. The row is marked as retrying, or failed when the queue gives up.
     */
    public function attemptFailed(Throwable $exception, bool $willRetry): void
    {
        foreach (array_filter([$this->current, $this->currentPlainEmail]) as $row) {
            $row->update([
                'status' => $willRetry ? NotificationStatus::Retrying : NotificationStatus::Failed,
                'error' => $this->describe($exception),
                'failed_at' => $willRetry ? null : now(),
            ]);
        }

        $this->reset();
    }

    /**
     * The queue gave up on a job. This also covers failures that happen before sending starts,
     * such as running out of attempts after a timeout.
     *
     * @param  array<string, mixed>  $payload
     */
    public function jobFailed(array $payload, Throwable $exception): void
    {
        if (($payload['data']['commandName'] ?? null) !== SendQueuedNotifications::class) {
            return;
        }

        try {
            $command = unserialize($payload['data']['command'], ['allowed_classes' => true]);
        } catch (Throwable) {
            return;
        }

        if (! $command instanceof SendQueuedNotifications || ! $command->notification->id) {
            return;
        }

        NotificationLog::query()
            ->where('notification_id', $command->notification->id)
            ->where('status', '!=', NotificationStatus::Sent)
            ->update([
                'status' => NotificationStatus::Failed,
                'error' => $this->describe($exception),
                'failed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function jobStarted(): void
    {
        $this->reset();
        $this->insideQueuedJob = true;
    }

    public function reset(): void
    {
        $this->current = null;
        $this->currentPlainEmail = null;
        $this->insideQueuedJob = false;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function record(Notification $notification, object $notifiable, string $channel, array $attributes): NotificationLog
    {
        $row = NotificationLog::firstOrNew([
            'notification_id' => $notification->id,
            'channel' => $channel,
        ]);

        $row->fill([
            'type' => NotificationType::keyFor($notification),
            'recipient' => $row->recipient ?? $this->recipientFor($notifiable, $notification, $channel),
            ...$attributes,
        ]);

        if ($notifiable instanceof Model) {
            $row->notifiable()->associate($notifiable);
        }

        $row->save();

        if ($row->wasRecentlyCreated && $notification instanceof HasLogSubjects) {
            $this->linkSubjects($row, $notification);
        }

        return $row;
    }

    private function find(Notification $notification, string $channel): ?NotificationLog
    {
        if (! $notification->id) {
            return null;
        }

        return NotificationLog::query()
            ->where('notification_id', $notification->id)
            ->where('channel', $channel)
            ->first();
    }

    private function linkSubjects(NotificationLog $row, HasLogSubjects $notification): void
    {
        DB::table('notification_log_subjects')->insertOrIgnore(
            $notification->logSubjects()
                ->map(fn (Model $subject) => [
                    'notification_log_id' => $row->id,
                    'subject_type' => $subject->getMorphClass(),
                    'subject_id' => $subject->getKey(),
                ])
                ->all()
        );
    }

    private function recipientFor(object $notifiable, Notification $notification, string $channel): ?string
    {
        if (! method_exists($notifiable, 'routeNotificationFor')) {
            return null;
        }

        $route = $notifiable->routeNotificationFor($channel, $notification);

        if (is_array($route)) {
            $route = implode(', ', array_map(
                static fn ($key, $value) => is_string($key) ? $key : (string) $value,
                array_keys($route),
                $route,
            ));
        }

        return is_string($route) ? Str::limit($route, 250) : null;
    }

    /**
     * @param  list<Address>  $addresses
     */
    private function addresses(array $addresses): ?string
    {
        $list = implode(', ', array_map(static fn (Address $address) => $address->getAddress(), $addresses));

        return $list === '' ? null : Str::limit($list, 250);
    }

    private function describe(Throwable $exception): string
    {
        return Str::limit(class_basename($exception).': '.$exception->getMessage(), 2000);
    }
}
