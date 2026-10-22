<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        EmailTemplate::query()->upsert([
            [
                'key' => 'shift-assignments-released',
                'name' => 'Shift Assignments Released',
                'subject' => '{{ app.site_name }} Shift Assignments: {{ period.label }}',
                'body' => <<<'BODY'
Dear {{ user.name }},

This email is for informational purposes only—no reply is necessary or expected.

We're pleased to inform you that you have been assigned to the following SMPW cart shift(s):

{{#each shifts}}
**Date:** {{ shift.date }}<br>
**Location:** {{ shift.location }}{{#if shift.location_map_url}} ([Map]({{ shift.location_map_url }})){{/if}}<br>
{{#if shift.location_description}}{{ shift.location_description }}<br>
{{/if}}**Start Time:** {{ shift.start_time }}<br>
**Finish Time:** {{ shift.end_time }}<br>
**Other Volunteers:**

<ul>
{{#each shift.other_volunteers}}
<li>{{ volunteer.name }}{{#if volunteer.mobile_phone}} — {{ volunteer.mobile_phone }}{{/if}}</li>
{{#empty}}
<li>None</li>
{{/each}}
</ul>

{{/each}}

Your shift details are now available for review via the Cart Scheduler app.

**Important Reminders:**

**Withdrawing from a Shift:**
If you need to withdraw, please contact all other volunteers assigned to that shift before removing your name. This applies to both assigned and claimed shifts, at any time before the scheduled shift.

**Pre-Shift Communication:**
Please reach out to all members of your shift the day before to confirm meeting time and location. Sisters, if you haven't heard from the lead brother by 5:00 PM the evening before, kindly take the initiative to contact him.

Thank you for your reliable support and clear communication with your teammates - Prov. 21:5.

Keep courageous and faithful,

SMPW Admin Team

[www.smpwmelbourne.org](https://www.smpwmelbourne.org)
"Wisdom cries aloud in the street… at the entrance of the city gates she speaks."
— Proverbs 1:20–21
BODY,
                'description' => 'Emailed to volunteers who were pre-assigned to shifts when a new reservation period is released.',
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'user-account-created',
                'name' => 'User Account Created',
                'subject' => '{{ app.site_name }} Account Activation',
                'body' => <<<'BODY'
# {{ app.site_name }} Account Activation

Dear {{ user.name }}, an account has been created for you on the {{ app.site_name }} Public Witnessing web application.

If you feel this is an error, please [contact us immediately](mailto:{{ mail.support.address }}?subject=Mistaken%20User%20Account). Otherwise, please use the verification link below.

{{ confirm_account_button }}

If the button does not work, copy this link into your browser:
{{ confirm_account_url }}

Thank you,<br>
The {{ app.site_name }} Team
BODY,
                'description' => 'Sent when a new user account is created with a set-password link.',
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['key'], ['name', 'subject', 'body', 'description', 'is_system', 'updated_at']);
    }
}
