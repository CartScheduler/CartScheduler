{{--@formatter:off--}}
@component('mail::message')

Dear {{ $user->name }},

This email is for informational purposes only—no reply is necessary or expected.

We’re pleased to inform you that you have been assigned to the following SMPW cart shift{{ $shifts->count() > 1 ? 's' : '' }}:

@foreach ($shifts as $shift)
**Date:** {{ $shift['date'] }}  
**Location:** {{ $shift['location'] }}  
**Start Time:** {{ $shift['start_time'] }}  
**Finish Time:** {{ $shift['end_time'] }}  
**Other Volunteers:**

<ul>
@forelse ($shift['other_volunteers'] as $volunteer)
<li>{{ $volunteer['name'] }}@if ($volunteer['mobile_phone']) — {{ $volunteer['mobile_phone'] }}@endif</li>
@empty
<li>None</li>
@endforelse
</ul>

@endforeach

Your shift details are now available for review via the Cart Scheduler app.

**Important Reminders:**

**Withdrawing from a Shift:**  
If you need to withdraw, please contact all other volunteers assigned to that shift before removing your name. This applies to both assigned and claimed shifts, at any time before the scheduled shift.

**Pre-Shift Communication:**  
Please reach out to all members of your shift the day before to confirm meeting time and location. Sisters, if you haven’t heard from the lead brother by 5:00 PM the evening before, kindly take the initiative to contact him.

Thank you for your reliable support and clear communication with your teammates - Prov. 21:5.

Keep courageous and faithful,

SMPW Admin Team

[www.smpwmelbourne.org](https://www.smpwmelbourne.org)  
“Wisdom cries aloud in the street… at the entrance of the city gates she speaks.”  
— Proverbs 1:20–21

@endcomponent
