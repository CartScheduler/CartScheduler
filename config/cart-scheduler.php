<?php

return [
    'version'                           => env('SELF_UPDATER_VERSION_INSTALLED', '0.0.1'),
    'shift_reservation_duration'        => (int) env('CA_SHIFT_RESERVATION_DURATION', 1),
    'shift_reservation_duration_period' => env('CA_SHIFT_RESERVATION_DURATION_PERIOD', 'MONTH'),
    'release_weekly_shifts_on_day'      => env('CA_SHIFT_RESERVATION_WEEK_RELEASE_DAY', 'SUN'),
    'release_new_shifts_at_time'        => env('CA_SHIFT_RESERVATION_NEW_RELEASE_TIME', '00:00:00'),
    'do_release_shifts_daily'           => env('CA_SHIFT_RESERVATION_DAILY_RELEASE_SCHEDULE', false),
    'max_volunteers_per_location'       => (int) env('CA_MAX_VOLUNTEERS_PER_SHIFT', 4),

    // Formatted this way as a very primitive obfuscation
    'set_password_generic_error_message' =>
        'An unknown error occurred. Did you click on a link to set your password? If not, please do so to proceed.',
];
