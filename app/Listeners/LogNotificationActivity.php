<?php

namespace App\Listeners;

use App\Notifications\NotificationLogger;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;

/**
 * Feeds notification, email and queue events into the notification log. Registered by event discovery.
 */
readonly class LogNotificationActivity
{
    public function __construct(private NotificationLogger $logger) {}

    public function handleJobQueued(JobQueued $event): void
    {
        if ($event->job instanceof SendQueuedNotifications) {
            $this->logger->queued($event->job);
        }
    }

    public function handleJobProcessing(JobProcessing $event): void
    {
        $this->logger->jobStarted();
    }

    public function handleJobProcessed(JobProcessed $event): void
    {
        $this->logger->reset();
    }

    public function handleNotificationSending(NotificationSending $event): ?bool
    {
        return $this->logger->sending($event);
    }

    public function handleMessageSending(MessageSending $event): void
    {
        $this->logger->emailSending($event);
    }

    public function handleMessageSent(MessageSent $event): void
    {
        $this->logger->emailSent($event);
    }

    public function handleNotificationSent(NotificationSent $event): void
    {
        $this->logger->sent($event);
    }

    public function handleNotificationFailed(NotificationFailed $event): void
    {
        $this->logger->failed($event);
    }

    public function handleJobExceptionOccurred(JobExceptionOccurred $event): void
    {
        $this->logger->attemptFailed($event->exception, willRetry: ! $event->job->hasFailed());
    }

    public function handleJobFailed(JobFailed $event): void
    {
        $this->logger->jobFailed($event->job->payload(), $event->exception);
    }
}
