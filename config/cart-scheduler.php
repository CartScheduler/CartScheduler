<?php

return [
    'version' => env('SELF_UPDATER_VERSION_INSTALLED', '0.0.1'),
    'shift_reservation_duration' => (int) env('CA_SHIFT_RESERVATION_DURATION', 1),
    'shift_reservation_duration_period' => env('CA_SHIFT_RESERVATION_DURATION_PERIOD', 'MONTH'),
    'release_weekly_shifts_on_day' => env('CA_SHIFT_RESERVATION_WEEK_RELEASE_DAY', 'SUN'),
    'release_new_shifts_at_time' => env('CA_SHIFT_RESERVATION_NEW_RELEASE_TIME', '00:00:00'),
    'do_release_shifts_daily' => env('CA_SHIFT_RESERVATION_DAILY_RELEASE_SCHEDULE', false),
    'max_volunteers_per_location' => (int) env('CA_MAX_VOLUNTEERS_PER_SHIFT', 4),
    'shift_assignment_notifications_enabled' => env('CA_SHIFT_ASSIGNMENT_NOTIFICATIONS_ENABLED', false),

    // How long sent and failed notifications stay in the notification log
    'notification_log_retention_days' => (int) env('CA_NOTIFICATION_LOG_RETENTION_DAYS', 90),
    // How long failed queue jobs are kept so they can be retried. Their payloads hold personal details.
    'failed_job_retention_days' => (int) env('CA_FAILED_JOB_RETENTION_DAYS', 30),
    // Most emails the queue sends per minute. Shared hosts often limit hourly sending, e.g. 100-500/hour.
    'mail_rate_per_minute' => (int) env('CA_MAIL_RATE_PER_MINUTE', 30),
    // Whether the scheduler starts a queue worker every minute (for hosts without Supervisor, e.g. cPanel)
    'run_queue_worker_from_scheduler' => (bool) env('CA_RUN_QUEUE_WORKER_FROM_SCHEDULER', true),

    // Formatted this way as a very primitive obfuscation
    'set_password_generic_error_message' => 'An unknown error occurred. Did you click on a link to set your password? If not, please do so to proceed.',
];
