<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Binds `Tests\TestCase` to every test in both suites so Pest's function-style
| tests get the same application bootstrap the class-based tests had. Class
| based tests that extend `Tests\TestCase` themselves keep working unchanged,
| so the suite can hold both styles while the migration is in progress.
|
| `RefreshDatabase` is applied per file rather than globally: a handful of
| tests deliberately run without it, and binding it here would silently give
| them a transaction they never asked for.
|
*/

pest()->extend(Tests\TestCase::class)->in('Feature', 'Unit');
