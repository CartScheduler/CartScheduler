<?php

namespace App\Console\Commands;

use App\Actions\NotifyVolunteersOfReleasedShifts;
use Illuminate\Console\Command;

class NotifyReleasedShiftAssignmentsCommand extends Command
{
    protected $signature = 'cart-scheduler:notify-released-shifts
                            {--force : Notify for the current outer reservation slice even when it is not a release moment}';

    protected $description = 'Email volunteers who were pre-assigned to shifts in the newly released reservation period.';

    public function handle(NotifyVolunteersOfReleasedShifts $notifyVolunteersOfReleasedShifts): int
    {
        if (!config('cart-scheduler.shift_assignment_notifications_enabled')) {
            $this->info('Shift assignment notifications are disabled (CA_SHIFT_ASSIGNMENT_NOTIFICATIONS_ENABLED).');

            return self::SUCCESS;
        }

        $result = $notifyVolunteersOfReleasedShifts->execute($this->option('force'));

        if ($result['users_notified'] === 0) {
            $this->info('No shift assignment notifications to send.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Sent notifications to %d volunteer(s) covering %d assignment(s).',
            $result['users_notified'],
            $result['assignments_notified'],
        ));

        return self::SUCCESS;
    }
}
