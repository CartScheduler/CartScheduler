{{--@formatter:off--}}
@foreach ($shifts as $shift)
**Date:** {{ $shift['date'] }}<br>
**Location:** {{ $shift['location'] }}@if (!empty($shift['location_map_url'])) ([Map]({{ $shift['location_map_url'] }}))@endif<br>
@if (!empty($shift['location_description']))
{{ $shift['location_description'] }}<br>
@endif
**Start Time:** {{ $shift['start_time'] }}<br>
**Finish Time:** {{ $shift['end_time'] }}<br>
**Other Volunteers:**

<ul>
@forelse ($shift['other_volunteers'] as $volunteer)
    <li>{{ $volunteer['name'] }}@if ($volunteer['mobile_phone']) — {{ $volunteer['mobile_phone'] }}@endif</li>
@empty
    <li>None</li>
@endforelse
</ul>

@endforeach
