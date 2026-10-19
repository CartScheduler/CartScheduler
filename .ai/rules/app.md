---
paths:
  - 'app/**'
---

# App

## Known timezone bugs, and the local/CI timezone split
There are outstanding timezone bugs in the application, flagged 2026-09-11 and not yet diagnosed or fixed. Treat date and time handling as suspect until that work happens, and do not assume existing behaviour here is correct.

Related trap when verifying: CI copies `.env.example`, which sets `APP_TIMEZONE=UTC`. Local development uses `.env`, which sets `Australia/Melbourne`. A date-sensitive test can therefore pass locally and fail only on CI. The same two files also disagree on `CA_SHIFT_RESERVATION_DURATION` (1 MONTH vs 6 WEEK) and `CA_MAX_VOLUNTEERS_PER_SHIFT` (4 vs 5).

To reproduce CI's environment locally:

    docker exec -e APP_TIMEZONE=UTC -w /var/www/html cartapp-laravel.test-1 php artisan test
