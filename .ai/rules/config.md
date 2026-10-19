---
paths:
  - config/cache.php
---

# Config

## Cached PHP objects must be allow-listed
Laravel 13 gates cache unserialization through `cache.serializable_classes`. Anything stored in the cache as a PHP object must be listed there, or it silently comes back as `__PHP_Incomplete_Class` — no exception is thrown.

Today the only entry is `App\Data\FilledShiftData`, cached by `AdminDashboardController` via `Cache::flexible`. Add a class here whenever you cache a new Data object or Collection.

The default test store is `array`, which does not serialize, so it hides a missing entry. `tests/Feature/App/Admin/AdminDashboardCacheTest.php` runs against the `file` store for exactly this reason — extend that file rather than trusting a passing suite.
