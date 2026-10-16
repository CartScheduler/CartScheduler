<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\JetstreamServiceProvider;
use Bugsnag\BugsnagLaravel\BugsnagServiceProvider;

return [
    BugsnagServiceProvider::class,
    AppServiceProvider::class,
    AuthServiceProvider::class,
    FortifyServiceProvider::class,
    JetstreamServiceProvider::class,
];
