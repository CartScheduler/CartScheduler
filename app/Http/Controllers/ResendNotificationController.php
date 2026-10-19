<?php

namespace App\Http\Controllers;

use App\Actions\DescribeJobFailure;
use App\Actions\GetLaterNotificationAttempts;
use App\Actions\ResendNotification;
use App\Data\NotificationLogData;
use App\Http\Controllers\Concerns\EnsuresAllowedSettingsUser;
use App\Models\NotificationLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class ResendNotificationController extends Controller
{
    use EnsuresAllowedSettingsUser;

    /**
     * Sends a failed notification again, unless it has since been sent or can't be rebuilt.
     */
    public function __invoke(
        Request $request,
        NotificationLog $notificationLog,
        ResendNotification $resendNotification,
        GetLaterNotificationAttempts $getLaterNotificationAttempts,
        DescribeJobFailure $describeJobFailure,
    ): RedirectResponse {
        $this->ensureAllowedSettingsUser($request);

        $notificationLog->load(['notifiable', 'shiftUsers']);
        $laterAttempts = $getLaterNotificationAttempts->execute(collect([$notificationLog]));

        $data = NotificationLogData::fromModel($notificationLog, $laterAttempts[$notificationLog->id] ?? null);

        if (! $data->can_resend) {
            return $this->banner($data->cannot_resend_reason ?? "This email doesn't need sending again.", 'danger');
        }

        try {
            $resendNotification->execute($notificationLog);
        } catch (Throwable $exception) {
            // Without a queue the email is sent straight away, so a failure surfaces here.
            report($exception);

            return $this->banner('Sending failed again. '.$describeJobFailure->execute($exception->getMessage()), 'danger');
        }

        return $this->banner('The email is being sent again.', 'success');
    }

    private function banner(string $message, string $style): RedirectResponse
    {
        session()->flash('flash.banner', $message);
        session()->flash('flash.bannerStyle', $style);

        return back();
    }
}
