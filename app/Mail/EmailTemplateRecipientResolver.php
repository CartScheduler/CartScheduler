<?php

namespace App\Mail;

use App\Data\EmailTemplateRecipientData;

class EmailTemplateRecipientResolver
{
    public function for(string $templateKey): ?EmailTemplateRecipientData
    {
        return match ($templateKey) {
            'shift-assignments-released' => $this->shiftAssignmentsReleased(),
            'user-account-created' => $this->userAccountCreated(),
            default => null,
        };
    }

    private function shiftAssignmentsReleased(): EmailTemplateRecipientData
    {
        return new EmailTemplateRecipientData(
            summary: 'Each volunteer who was pre-assigned to one or more shifts in the newly released reservation window.',
        );
    }

    private function userAccountCreated(): EmailTemplateRecipientData
    {
        return new EmailTemplateRecipientData(
            summary: 'The user whose account was created, or whose welcome email is being resent.',
        );
    }
}
