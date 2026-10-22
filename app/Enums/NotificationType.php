<?php

namespace App\Enums;

use App\Mail\ShiftAssignmentsReleased;
use App\Mail\UserAccountCreated;
use App\Notifications\ShiftAssignmentsReleasedNotification;
use App\Notifications\UserAccountCreatedNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Str;
use Laravel\Jetstream\Mail\TeamInvitation;

enum NotificationType: string
{
    case ShiftAssignmentsReleased = 'shift-assignments-released';
    case UserAccountCreated = 'user-account-created';
    case PasswordReset = 'password-reset';
    case VerifyEmail = 'verify-email';
    case TeamInvitation = 'team-invitation';

    public function label(): string
    {
        return match ($this) {
            self::ShiftAssignmentsReleased => 'Shift Assignments Released',
            self::UserAccountCreated => 'User Account Created',
            self::PasswordReset => 'Password Reset',
            self::VerifyEmail => 'Verify Email Address',
            self::TeamInvitation => 'Team Invitation',
        };
    }

    /**
     * The stored type key for a notification or mailable class. Unknown classes get a kebab-case key
     * from their class name, so every email still appears in the log.
     */
    public static function keyFor(object|string $notificationOrMailable): string
    {
        $class = is_object($notificationOrMailable) ? $notificationOrMailable::class : $notificationOrMailable;

        $type = match ($class) {
            ShiftAssignmentsReleasedNotification::class, ShiftAssignmentsReleased::class => self::ShiftAssignmentsReleased,
            UserAccountCreatedNotification::class, UserAccountCreated::class => self::UserAccountCreated,
            ResetPassword::class => self::PasswordReset,
            VerifyEmail::class => self::VerifyEmail,
            TeamInvitation::class => self::TeamInvitation,
            default => null,
        };

        return $type->value ?? Str::kebab(Str::replaceLast('Notification', '', class_basename($class)));
    }

    public static function labelFor(string $key): string
    {
        return self::tryFrom($key)?->label() ?? Str::headline($key);
    }
}
