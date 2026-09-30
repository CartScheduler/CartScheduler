<?php

namespace App\Mail;

use App\Actions\MapDayOfWeek;
use App\Data\EmailTemplateTriggerData;
use App\Enums\DBPeriod;
use App\Enums\EmailTemplateTriggerType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

class EmailTemplateTriggerResolver
{
    public function __construct(private readonly MapDayOfWeek $mapDayOfWeek) {}

    public function for(string $templateKey): ?EmailTemplateTriggerData
    {
        return match ($templateKey) {
            'shift-assignments-released' => $this->shiftAssignmentsReleased(),
            'user-account-created' => $this->userAccountCreated(),
            default => null,
        };
    }

    private function shiftAssignmentsReleased(): EmailTemplateTriggerData
    {
        $enabled = $this->configBool('cart-scheduler.shift_assignment_notifications_enabled');
        $duration = (int) config('cart-scheduler.shift_reservation_duration');
        $period = DBPeriod::getConfigPeriod();
        $daily = $this->configBool('cart-scheduler.do_release_shifts_daily');
        $releaseDay = (string) config('cart-scheduler.release_weekly_shifts_on_day');
        $releaseTime = (string) config('cart-scheduler.release_new_shifts_at_time');
        $timezone = (string) config('app.timezone');
        $formattedTime = Carbon::now()->setTimeFromTimeString($releaseTime)->format('g:i A');
        $periodLabel = $period === DBPeriod::Week
            ? Str::plural('week', $duration)
            : Str::plural('month', $duration);
        $when = match (true) {
            $daily => "each day at {$formattedTime} ({$timezone})",
            $period === DBPeriod::Week => "every {$this->formattedReleaseDay($releaseDay)} at {$formattedTime} ({$timezone})",
            default => "on the 1st of each month at {$formattedTime} ({$timezone})",
        };

        return new EmailTemplateTriggerData(
            type: EmailTemplateTriggerType::System,
            enabled: $enabled,
            summary: "Sent {$when} when a new reservation window is released ({$duration} {$periodLabel} ahead).",
        );
    }

    private function userAccountCreated(): EmailTemplateTriggerData
    {
        return new EmailTemplateTriggerData(
            type: EmailTemplateTriggerType::System,
            enabled: true,
            summary: 'Sent when an administrator creates a new user account (including bulk import), and when the welcome email is resent.',
        );
    }

    private function formattedReleaseDay(string $day): string
    {
        try {
            return $this->mapDayOfWeek->lengthen($day);
        } catch (InvalidArgumentException) {
            return $day !== '' ? $day : 'the configured weekday';
        }
    }

    private function configBool(string $key): bool
    {
        $value = config($key);

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
