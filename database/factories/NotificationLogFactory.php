<?php

namespace Database\Factories;

use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NotificationLog>
 */
class NotificationLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'notification_id' => (string) Str::uuid(),
            'channel' => 'mail',
            'type' => NotificationType::UserAccountCreated->value,
            'recipient' => fake()->safeEmail(),
            'subject' => 'Cart Scheduler Account Activation',
            'status' => NotificationStatus::Sent,
            'attempts' => 1,
            'queued_at' => now(),
            'sent_at' => now(),
        ];
    }

    public function forUser(User $user): self
    {
        return $this->state(fn (array $attributes) => [
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'recipient' => $user->email,
        ]);
    }

    public function ofType(NotificationType $type): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type->value,
        ]);
    }

    public function queued(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => NotificationStatus::Queued,
            'attempts' => 0,
            'sent_at' => null,
        ]);
    }

    public function failed(string $error = 'TransportException: Connection could not be established with host "smtp.example.test:587"'): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => NotificationStatus::Failed,
            'error' => $error,
            'sent_at' => null,
            'failed_at' => now(),
        ]);
    }

    public function stalled(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => NotificationStatus::Sending,
            'sent_at' => null,
            'updated_at' => now()->subMinutes(NotificationLog::STALLED_AFTER_MINUTES + 5),
        ]);
    }
}
